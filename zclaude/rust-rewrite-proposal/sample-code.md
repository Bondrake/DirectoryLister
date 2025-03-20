# DirectoryLister Rust Sample Code

This document provides representative sample code for key components of the proposed Rust rewrite. These samples illustrate the architectural principles and implementation approach.

## Core Domain Examples

### File System Domain

```rust
// crates/core/src/domain/file_entry.rs

use std::path::{Path, PathBuf};
use chrono::{DateTime, Utc};

/// Enum representing file types
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash, Serialize, Deserialize)]
pub enum FileKind {
    File,
    Directory,
    Symlink,
}

/// File metadata information
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct FileMetadata {
    pub size: u64,
    pub modified: DateTime<Utc>,
    pub accessed: Option<DateTime<Utc>>,
    pub created: Option<DateTime<Utc>>,
    pub is_readonly: bool,
    pub is_hidden: bool,
}

/// Core file entry trait
#[async_trait]
pub trait FileEntry: Send + Sync {
    /// Get the file name
    fn name(&self) -> &str;
    
    /// Get the file path
    fn path(&self) -> &Path;
    
    /// Get the file kind
    fn kind(&self) -> FileKind;
    
    /// Get file metadata
    async fn metadata(&self) -> Result<FileMetadata, FileError>;
    
    /// Check if file is hidden according to rules
    fn is_hidden(&self, rules: &HiddenRules) -> bool;
    
    /// Get file content if applicable
    async fn content(&self) -> Result<Vec<u8>, FileError>;
    
    /// Get file content stream if applicable
    async fn content_stream(&self) -> Result<Box<dyn AsyncRead>, FileError>;
}
```

### File System Port

```rust
// crates/core/src/ports/file_system.rs

use std::path::{Path, PathBuf};
use crate::domain::file_entry::{FileEntry, FileKind, FileMetadata};
use crate::errors::FileError;
use async_trait::async_trait;

/// FileSystem port defining file system operations
#[async_trait]
pub trait FileSystem: Send + Sync + 'static {
    /// Check if a path exists
    async fn exists(&self, path: &Path) -> bool;
    
    /// Get a file entry for a path
    async fn entry(&self, path: &Path) -> Result<Box<dyn FileEntry>, FileError>;
    
    /// List contents of a directory
    async fn list_directory(&self, path: &Path) -> Result<Vec<Box<dyn FileEntry>>, FileError>;
    
    /// Get file metadata
    async fn metadata(&self, path: &Path) -> Result<FileMetadata, FileError>;
    
    /// Read file content
    async fn read_file(&self, path: &Path) -> Result<Vec<u8>, FileError>;
    
    /// Read file content as a stream
    async fn read_file_stream(&self, path: &Path) -> Result<Box<dyn AsyncRead>, FileError>;
    
    /// Resolve a relative path against the base path
    fn resolve_path(&self, path: &Path) -> PathBuf;
}
```

## Application Services Examples

### Directory Service

```rust
// crates/application/src/services/directory.rs

use std::path::{Path, PathBuf};
use std::sync::Arc;

use async_trait::async_trait;
use tracing::{debug, instrument};

use directory_lister_core::{
    domain::{FileEntry, FileKind, HiddenRules, SortMethod},
    ports::{CacheStore, FileSystem},
    errors::FileError,
};

use crate::{
    dto::directory::{DirectoryListing, ListingEntry, ReadmeContent},
    errors::ServiceError,
};

/// Interface for directory services
#[async_trait]
pub trait DirectoryService: Send + Sync + 'static {
    /// List contents of a directory
    async fn list_directory(
        &self, 
        path: &Path,
        sort_method: SortMethod,
        reverse: bool,
    ) -> Result<DirectoryListing, ServiceError>;
    
    /// Get README content if available
    async fn get_readme(&self, path: &Path) -> Result<Option<ReadmeContent>, ServiceError>;
}

/// Implementation of DirectoryService
pub struct DirectoryServiceImpl<FS, CS> 
where
    FS: FileSystem,
    CS: CacheStore,
{
    file_system: Arc<FS>,
    cache: Arc<CS>,
    hidden_rules: HiddenRules,
    config: DirectoryConfig,
}

impl<FS, CS> DirectoryServiceImpl<FS, CS>
where
    FS: FileSystem,
    CS: CacheStore,
{
    pub fn new(
        file_system: Arc<FS>,
        cache: Arc<CS>,
        hidden_rules: HiddenRules,
        config: DirectoryConfig,
    ) -> Self {
        Self {
            file_system,
            cache,
            hidden_rules,
            config,
        }
    }
    
    // Helper method to find README in a directory
    async fn find_readme(
        &self, 
        entries: &[Box<dyn FileEntry>]
    ) -> Result<Option<Box<dyn FileEntry>>, FileError> {
        // Implementation that finds the first README file
        let readme = entries.iter()
            .filter(|e| e.kind() == FileKind::File)
            .find(|e| {
                let name = e.name().to_lowercase();
                name == "readme.md" || name == "readme.txt" || name == "readme"
            })
            .cloned();
            
        Ok(readme)
    }
}

#[async_trait]
impl<FS, CS> DirectoryService for DirectoryServiceImpl<FS, CS>
where
    FS: FileSystem,
    CS: CacheStore,
{
    #[instrument(skip(self))]
    async fn list_directory(
        &self, 
        path: &Path,
        sort_method: SortMethod,
        reverse: bool,
    ) -> Result<DirectoryListing, ServiceError> {
        debug!("Listing directory: {}", path.display());
        
        // Check cache first
        let cache_key = format!(
            "dir:{}:{}:{}",
            path.display(),
            sort_method.as_str(),
            reverse
        );
        
        if let Some(cached) = self.cache.get(&cache_key).await? {
            return Ok(serde_json::from_slice(&cached)?);
        }
        
        // Get entries from file system
        let entries = self.file_system.list_directory(path).await?;
        
        // Process entries in parallel
        let processed_entries = tokio::task::spawn_blocking(move || {
            entries.into_par_iter()
                .filter(|e| !self.hidden_rules.is_hidden(e))
                .map(|e| self.process_entry(e))
                .collect::<Result<Vec<_>, _>>()
        }).await??;
        
        // Sort entries
        let mut sorted_entries = processed_entries;
        self.sort_entries(&mut sorted_entries, sort_method);
        
        if reverse {
            sorted_entries.reverse();
        }
        
        // Find README if needed
        let readme = if self.config.display_readmes {
            self.get_readme(path).await?
        } else {
            None
        };
        
        // Create result
        let result = DirectoryListing {
            path: path.to_path_buf(),
            parent_path: path.parent().map(PathBuf::from),
            entries: sorted_entries,
            readme,
        };
        
        // Cache result
        self.cache.set(
            &cache_key,
            serde_json::to_vec(&result)?,
            self.config.cache_ttl,
        ).await?;
        
        Ok(result)
    }
    
    #[instrument(skip(self))]
    async fn get_readme(&self, path: &Path) -> Result<Option<ReadmeContent>, ServiceError> {
        // Implementation to find and render README
        // ...
        
        Ok(None) // Placeholder
    }
}
```

### ZIP Service

```rust
// crates/application/src/services/zip.rs

use std::path::{Path, PathBuf};
use std::sync::Arc;

use async_trait::async_trait;
use bytes::Bytes;
use futures::{Stream, StreamExt};
use tokio::sync::Semaphore;
use tracing::{debug, instrument};

use directory_lister_core::{
    ports::FileSystem,
    errors::FileError,
};

use crate::{
    errors::ServiceError,
};

/// Stream that generates a ZIP file progressively
pub struct ZipStream<FS: FileSystem> {
    file_system: Arc<FS>,
    base_path: PathBuf,
    entries: Vec<PathBuf>,
    current_index: usize,
    compress: bool,
    zip_builder: ZipBuilder,
}

impl<FS: FileSystem> ZipStream<FS> {
    pub async fn new(
        file_system: Arc<FS>,
        path: &Path,
        compress: bool,
    ) -> Result<Self, ServiceError> {
        // Collect all entries recursively
        let entries = Self::collect_entries(file_system.clone(), path).await?;
        
        Ok(Self {
            file_system,
            base_path: path.to_path_buf(),
            entries,
            current_index: 0,
            compress,
            zip_builder: ZipBuilder::new(),
        })
    }
    
    // Helper to collect all files recursively
    async fn collect_entries(
        file_system: Arc<FS>,
        path: &Path,
    ) -> Result<Vec<PathBuf>, ServiceError> {
        // Implementation to collect all file paths
        // ...
        
        Ok(vec![]) // Placeholder
    }
}

impl<FS: FileSystem> Stream for ZipStream<FS> {
    type Item = Result<Bytes, ServiceError>;
    
    fn poll_next(
        mut self: std::pin::Pin<&mut Self>,
        cx: &mut std::task::Context<'_>,
    ) -> std::task::Poll<Option<Self::Item>> {
        // Implementation to progressively generate ZIP chunks
        // ...
        
        std::task::Poll::Ready(None) // Placeholder
    }
}

/// Interface for ZIP services
#[async_trait]
pub trait ZipService: Send + Sync + 'static {
    /// Generate a ZIP archive for a directory
    async fn generate_zip(
        &self,
        path: &Path,
        compress: bool,
    ) -> Result<impl Stream<Item = Result<Bytes, ServiceError>> + Send, ServiceError>;
}

/// Implementation of ZipService
pub struct ZipServiceImpl<FS: FileSystem> {
    file_system: Arc<FS>,
    config: ZipConfig,
    semaphore: Arc<Semaphore>, // Limit concurrent ZIP operations
}

impl<FS: FileSystem> ZipServiceImpl<FS> {
    pub fn new(
        file_system: Arc<FS>,
        config: ZipConfig,
    ) -> Self {
        Self {
            file_system,
            config,
            semaphore: Arc::new(Semaphore::new(config.max_concurrent_zips)),
        }
    }
}

#[async_trait]
impl<FS: FileSystem> ZipService for ZipServiceImpl<FS> {
    #[instrument(skip(self))]
    async fn generate_zip(
        &self,
        path: &Path,
        compress: bool,
    ) -> Result<impl Stream<Item = Result<Bytes, ServiceError>> + Send, ServiceError> {
        debug!("Generating ZIP for: {}", path.display());
        
        // Acquire permit to limit concurrent operations
        let _permit = self.semaphore.acquire().await.map_err(|_| {
            ServiceError::LimitExceeded("Too many concurrent ZIP operations".to_string())
        })?;
        
        // Create ZIP stream
        let stream = ZipStream::new(self.file_system.clone(), path, compress).await?;
        
        Ok(stream)
    }
}
```

## Infrastructure Examples

### Native File System Adapter

```rust
// crates/infrastructure/src/adapters/file_system/native.rs

use std::path::{Path, PathBuf};
use std::sync::Arc;

use async_trait::async_trait;
use tokio::fs;
use tracing::instrument;

use directory_lister_core::{
    domain::{FileEntry, FileKind, FileMetadata, HiddenRules},
    ports::FileSystem,
    errors::FileError,
};

/// Implementation of FileEntry for the native file system
pub struct NativeFileEntry {
    path: PathBuf,
    name: String,
    fs: Arc<NativeFileSystem>,
}

#[async_trait]
impl FileEntry for NativeFileEntry {
    fn name(&self) -> &str {
        &self.name
    }
    
    fn path(&self) -> &Path {
        &self.path
    }
    
    fn kind(&self) -> FileKind {
        // Determine kind based on path
        if self.path.is_dir() {
            FileKind::Directory
        } else if self.path.is_symlink() {
            FileKind::Symlink
        } else {
            FileKind::File
        }
    }
    
    #[instrument(skip(self))]
    async fn metadata(&self) -> Result<FileMetadata, FileError> {
        // Get metadata from path
        let metadata = fs::metadata(&self.path).await
            .map_err(|e| FileError::IoError(e.to_string()))?;
            
        // Convert to domain metadata
        Ok(FileMetadata {
            size: metadata.len(),
            modified: metadata.modified()
                .map_err(|e| FileError::IoError(e.to_string()))?
                .into(),
            accessed: metadata.accessed()
                .ok()
                .map(|t| t.into()),
            created: metadata.created()
                .ok()
                .map(|t| t.into()),
            is_readonly: metadata.permissions().readonly(),
            is_hidden: self.fs.is_hidden(&self.path),
        })
    }
    
    fn is_hidden(&self, rules: &HiddenRules) -> bool {
        rules.is_hidden(self.path())
    }
    
    #[instrument(skip(self))]
    async fn content(&self) -> Result<Vec<u8>, FileError> {
        if self.kind() != FileKind::File {
            return Err(FileError::NotAFile(self.path().to_string_lossy().to_string()));
        }
        
        fs::read(&self.path).await
            .map_err(|e| FileError::IoError(e.to_string()))
    }
    
    #[instrument(skip(self))]
    async fn content_stream(&self) -> Result<Box<dyn AsyncRead>, FileError> {
        if self.kind() != FileKind::File {
            return Err(FileError::NotAFile(self.path().to_string_lossy().to_string()));
        }
        
        let file = fs::File::open(&self.path).await
            .map_err(|e| FileError::IoError(e.to_string()))?;
            
        Ok(Box::new(file))
    }
}

/// Native file system implementation
pub struct NativeFileSystem {
    base_path: PathBuf,
    hidden_patterns: Vec<String>,
}

impl NativeFileSystem {
    pub fn new(base_path: PathBuf, hidden_patterns: Vec<String>) -> Self {
        Self {
            base_path,
            hidden_patterns,
        }
    }
    
    fn is_hidden(&self, path: &Path) -> bool {
        // Check if file is hidden according to OS rules
        #[cfg(target_os = "windows")]
        {
            use std::os::windows::fs::MetadataExt;
            if let Ok(metadata) = std::fs::metadata(path) {
                let attributes = metadata.file_attributes();
                if attributes & 0x2 != 0 {
                    return true;
                }
            }
        }
        
        #[cfg(unix)]
        {
            if let Some(file_name) = path.file_name() {
                if let Some(name) = file_name.to_str() {
                    if name.starts_with('.') {
                        return true;
                    }
                }
            }
        }
        
        // Check against hidden patterns
        for pattern in &self.hidden_patterns {
            if pattern_match(pattern, path) {
                return true;
            }
        }
        
        false
    }
}

#[async_trait]
impl FileSystem for NativeFileSystem {
    #[instrument(skip(self))]
    async fn exists(&self, path: &Path) -> bool {
        let full_path = self.resolve_path(path);
        tokio::fs::metadata(&full_path).await.is_ok()
    }
    
    #[instrument(skip(self))]
    async fn entry(&self, path: &Path) -> Result<Box<dyn FileEntry>, FileError> {
        let full_path = self.resolve_path(path);
        
        // Ensure path exists
        if !self.exists(path).await {
            return Err(FileError::NotFound(path.to_string_lossy().to_string()));
        }
        
        let name = full_path.file_name()
            .and_then(|n| n.to_str())
            .unwrap_or("")
            .to_string();
            
        Ok(Box::new(NativeFileEntry {
            path: full_path,
            name,
            fs: Arc::new(self.clone()),
        }))
    }
    
    #[instrument(skip(self))]
    async fn list_directory(&self, path: &Path) -> Result<Vec<Box<dyn FileEntry>>, FileError> {
        let full_path = self.resolve_path(path);
        
        // Ensure path is a directory
        let metadata = fs::metadata(&full_path).await
            .map_err(|e| FileError::IoError(e.to_string()))?;
            
        if !metadata.is_dir() {
            return Err(FileError::NotADirectory(path.to_string_lossy().to_string()));
        }
        
        // Read directory entries
        let mut entries = Vec::new();
        let mut read_dir = fs::read_dir(&full_path).await
            .map_err(|e| FileError::IoError(e.to_string()))?;
            
        while let Some(entry) = read_dir.next_entry().await
            .map_err(|e| FileError::IoError(e.to_string()))? {
            
            let path = entry.path();
            let name = path.file_name()
                .and_then(|n| n.to_str())
                .unwrap_or("")
                .to_string();
                
            entries.push(Box::new(NativeFileEntry {
                path,
                name,
                fs: Arc::new(self.clone()),
            }) as Box<dyn FileEntry>);
        }
        
        Ok(entries)
    }
    
    // Other method implementations...
    
    fn resolve_path(&self, path: &Path) -> PathBuf {
        // Security check to prevent path traversal
        let normalized = normalize_path(path);
        self.base_path.join(normalized)
    }
}

// Helper function to normalize a path and prevent path traversal
fn normalize_path(path: &Path) -> PathBuf {
    // Implementation to normalize path
    // ...
    
    PathBuf::from(path) // Placeholder
}

// Helper function to match a glob pattern against a path
fn pattern_match(pattern: &str, path: &Path) -> bool {
    // Implementation for glob matching
    // ...
    
    false // Placeholder
}
```

## Web Interface Examples

### Directory API Handler

```rust
// crates/web/src/api/directory.rs

use std::path::{Path, PathBuf};
use std::sync::Arc;

use axum::{
    extract::{Path as PathExtractor, Query, State},
    http::StatusCode,
    response::IntoResponse,
    Json,
};
use serde::Deserialize;
use tracing::instrument;

use directory_lister_application::{
    services::DirectoryService,
    dto::directory::DirectoryListing,
    errors::ServiceError,
};

use crate::AppState;

#[derive(Debug, Deserialize)]
pub struct DirectoryQuery {
    #[serde(default = "default_sort")]
    sort: String,
    #[serde(default = "default_reverse")]
    reverse: bool,
}

fn default_sort() -> String {
    "name".to_string()
}

fn default_reverse() -> bool {
    false
}

/// Handler for listing directory contents
#[instrument(skip(state))]
pub async fn list_directory(
    PathExtractor(path): PathExtractor<String>,
    Query(query): Query<DirectoryQuery>,
    State(state): State<Arc<AppState>>,
) -> impl IntoResponse {
    // Decode path and normalize
    let path = percent_decode(&path).unwrap_or_else(|| path.clone());
    let path = PathBuf::from(path);
    
    // Convert sort method
    let sort_method = match query.sort.as_str() {
        "name" => SortMethod::Name,
        "size" => SortMethod::Size,
        "type" => SortMethod::Type,
        "modified" => SortMethod::Modified,
        _ => SortMethod::Name,
    };
    
    // Get directory listing
    match state.directory_service.list_directory(&path, sort_method, query.reverse).await {
        Ok(listing) => {
            // Check if HTML or JSON is requested
            if wants_html() {
                // Render HTML template
                match render_directory_template(&listing) {
                    Ok(html) => (StatusCode::OK, html).into_response(),
                    Err(_) => (StatusCode::INTERNAL_SERVER_ERROR, "Error rendering template").into_response(),
                }
            } else {
                // Return JSON
                (StatusCode::OK, Json(listing)).into_response()
            }
        },
        Err(err) => {
            let status = match err {
                ServiceError::NotFound(_) => StatusCode::NOT_FOUND,
                ServiceError::PermissionDenied(_) => StatusCode::FORBIDDEN,
                _ => StatusCode::INTERNAL_SERVER_ERROR,
            };
            
            (status, err.to_string()).into_response()
        }
    }
}

/// Helper function to render directory listing template
fn render_directory_template(listing: &DirectoryListing) -> Result<String, askama::Error> {
    let template = DirectoryTemplate { listing };
    template.render()
}

/// Helper function to check if client wants HTML
fn wants_html() -> bool {
    // Implementation to check Accept header
    // ...
    
    true // Placeholder
}

/// Helper function to decode URL percent encoding
fn percent_decode(input: &str) -> Option<String> {
    // Implementation to decode URL
    // ...
    
    Some(input.to_string()) // Placeholder
}

/// Template for directory listing
#[derive(Template)]
#[template(path = "directory.html")]
struct DirectoryTemplate<'a> {
    listing: &'a DirectoryListing,
}
```

## CLI Interface Examples

### List Command

```rust
// crates/cli/src/commands/list.rs

use std::path::PathBuf;
use std::sync::Arc;

use clap::Args;
use tracing::instrument;

use directory_lister_application::{
    services::DirectoryService,
    dto::directory::DirectoryListing,
    errors::ServiceError,
};

use crate::{
    ui::directory::DirectoryDisplay,
    errors::CliError,
};

#[derive(Debug, Args)]
pub struct ListArgs {
    /// Directory path to list
    #[arg(default_value = ".")]
    pub path: PathBuf,
    
    /// Sort method (name, size, type, modified)
    #[arg(long, short, default_value = "name")]
    pub sort: String,
    
    /// Reverse sort order
    #[arg(long, short)]
    pub reverse: bool,
    
    /// Show hidden files
    #[arg(long, short)]
    pub all: bool,
}

pub struct ListCommand {
    service: Arc<dyn DirectoryService>,
}

impl ListCommand {
    pub fn new(service: Arc<dyn DirectoryService>) -> Self {
        Self { service }
    }
    
    #[instrument(skip(self))]
    pub async fn execute(&self, args: ListArgs) -> Result<(), CliError> {
        // Convert sort method
        let sort_method = match args.sort.as_str() {
            "name" => SortMethod::Name,
            "size" => SortMethod::Size,
            "type" => SortMethod::Type,
            "modified" => SortMethod::Modified,
            _ => SortMethod::Name,
        };
        
        // Get directory listing
        let listing = self.service
            .list_directory(&args.path, sort_method, args.reverse)
            .await
            .map_err(CliError::from)?;
        
        // Display the listing
        let display = DirectoryDisplay::new(!args.all);
        display.show(&listing)?;
        
        Ok(())
    }
}

/// Terminal UI implementation for directory display
pub struct DirectoryDisplay {
    hide_hidden: bool,
}

impl DirectoryDisplay {
    pub fn new(hide_hidden: bool) -> Self {
        Self { hide_hidden }
    }
    
    pub fn show(&self, listing: &DirectoryListing) -> Result<(), CliError> {
        // Implementation to display listing in terminal
        // ...
        
        Ok(()) // Placeholder
    }
}
```

## Testing Examples

### File System Test

```rust
// crates/infrastructure/src/adapters/file_system/native.rs

#[cfg(test)]
mod tests {
    use super::*;
    use tempfile::TempDir;
    use tokio::fs;
    use tokio::io::AsyncWriteExt;
    
    async fn setup_test_directory() -> (TempDir, NativeFileSystem) {
        let temp_dir = TempDir::new().unwrap();
        let base_path = temp_dir.path().to_path_buf();
        
        // Create test files
        let test_file_path = base_path.join("test.txt");
        let mut file = fs::File::create(&test_file_path).await.unwrap();
        file.write_all(b"test content").await.unwrap();
        file.flush().await.unwrap();
        
        // Create test directory
        let test_dir_path = base_path.join("test_dir");
        fs::create_dir(&test_dir_path).await.unwrap();
        
        // Create hidden file
        let hidden_file_path = base_path.join(".hidden");
        let mut hidden_file = fs::File::create(&hidden_file_path).await.unwrap();
        hidden_file.write_all(b"hidden content").await.unwrap();
        hidden_file.flush().await.unwrap();
        
        let fs = NativeFileSystem::new(base_path, vec![String::from("*.hidden")]);
        
        (temp_dir, fs)
    }
    
    #[tokio::test]
    async fn test_list_directory() {
        let (_temp_dir, fs) = setup_test_directory().await;
        
        let entries = fs.list_directory(Path::new(".")).await.unwrap();
        
        // Should have at least test.txt and test_dir
        assert!(entries.len() >= 2);
        
        // Find test.txt
        let test_file = entries.iter()
            .find(|e| e.name() == "test.txt")
            .expect("test.txt should exist");
            
        assert_eq!(test_file.kind(), FileKind::File);
        
        // Find test_dir
        let test_dir = entries.iter()
            .find(|e| e.name() == "test_dir")
            .expect("test_dir should exist");
            
        assert_eq!(test_dir.kind(), FileKind::Directory);
    }
    
    #[tokio::test]
    async fn test_hidden_files() {
        let (_temp_dir, fs) = setup_test_directory().await;
        
        let entries = fs.list_directory(Path::new(".")).await.unwrap();
        
        // Find .hidden file
        let hidden_file = entries.iter()
            .find(|e| e.name() == ".hidden");
            
        assert!(hidden_file.is_some(), ".hidden should exist");
        
        let hidden_rules = HiddenRules::new(vec![".*".to_string(), "*.hidden".to_string()]);
        
        assert!(hidden_file.unwrap().is_hidden(&hidden_rules));
    }
    
    #[tokio::test]
    async fn test_file_content() {
        let (_temp_dir, fs) = setup_test_directory().await;
        
        let entry = fs.entry(Path::new("test.txt")).await.unwrap();
        let content = entry.content().await.unwrap();
        
        assert_eq!(content, b"test content");
    }
}
```

These examples demonstrate the clean architecture principles, separation of concerns, and orthogonal design of the proposed Rust rewrite. The code emphasizes type safety, proper error handling, and performance optimizations through parallel processing and efficient resource management.