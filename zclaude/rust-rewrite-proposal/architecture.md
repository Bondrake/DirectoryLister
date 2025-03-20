# DirectoryLister Rust Architecture

## Architecture Overview

The new Rust-based DirectoryLister follows a clean, hexagonal architecture pattern (also known as ports and adapters) to maximize orthogonality and separation of concerns. This document outlines the detailed architecture design.

## System Boundaries

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                      Domain Core                        │    │
│  │                                                         │    │
│  │   ┌───────────────┐          ┌───────────────────┐      │    │
│  │   │ File System   │          │ Directory Listing │      │    │
│  │   │ Domain        │          │ Domain            │      │    │
│  │   └───────────────┘          └───────────────────┘      │    │
│  │                                                         │    │
│  │   ┌───────────────┐          ┌───────────────────┐      │    │
│  │   │ Search        │          │ Content Rendering │      │    │
│  │   │ Domain        │          │ Domain            │      │    │
│  │   └───────────────┘          └───────────────────┘      │    │
│  │                                                         │    │
│  └────────────────────────┬────────────────────────────────┘    │
│                           │                                     │
│  ┌────────────────────────▼────────────────────────────────┐    │
│  │                     Application Services                 │    │
│  │                                                          │    │
│  │   ┌────────────────┐   ┌────────────────┐   ┌─────────┐  │    │
│  │   │ Directory      │   │ ZIP Generator  │   │ Search  │  │    │
│  │   │ Service        │   │ Service        │   │ Service │  │    │
│  │   └────────────────┘   └────────────────┘   └─────────┘  │    │
│  │                                                          │    │
│  │   ┌────────────────┐   ┌────────────────┐   ┌─────────┐  │    │
│  │   │ File Info      │   │ Markdown       │   │ Config  │  │    │
│  │   │ Service        │   │ Service        │   │ Service │  │    │
│  │   └────────────────┘   └────────────────┘   └─────────┘  │    │
│  │                                                          │    │
│  └──────────┬──────────────────────────────────┬────────────┘    │
│             │                                  │                 │
│  ┌──────────▼───────────┐        ┌─────────────▼──────────────┐  │
│  │      Adapters        │        │          Adapters          │  │
│  │      (Primary)       │        │         (Secondary)        │  │
│  │                      │        │                            │  │
│  │  ┌────────────┐      │        │   ┌────────────────────┐   │  │
│  │  │ Web API    │      │        │   │ Native File System │   │  │
│  │  └────────────┘      │        │   └────────────────────┘   │  │
│  │                      │        │                            │  │
│  │  ┌────────────┐      │        │   ┌────────────────────┐   │  │
│  │  │ CLI        │      │        │   │ Cache System       │   │  │
│  │  └────────────┘      │        │   └────────────────────┘   │  │
│  │                      │        │                            │  │
│  │  ┌────────────┐      │        │   ┌────────────────────┐   │  │
│  │  │ WebSockets │      │        │   │ Config Storage     │   │  │
│  │  └────────────┘      │        │   └────────────────────┘   │  │
│  │                      │        │                            │  │
│  └──────────────────────┘        └────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Layer Definitions

### Domain Core

The heart of the system containing pure business logic with no external dependencies.

#### File System Domain

```rust
pub trait FileEntry {
    fn name(&self) -> &str;
    fn path(&self) -> &Path;
    fn kind(&self) -> FileKind;
    fn metadata(&self) -> &FileMetadata;
    fn is_hidden(&self, rules: &HiddenRules) -> bool;
}

pub trait FileMetadata {
    fn size(&self) -> u64;
    fn modified_time(&self) -> DateTime<Utc>;
    fn access_time(&self) -> DateTime<Utc>;
    fn creation_time(&self) -> DateTime<Utc>;
    fn hash(&self, algorithm: HashAlgorithm) -> Result<String, FileError>;
}

pub trait ReadableFile {
    fn content(&self) -> Result<FileContent, FileError>;
    fn reader(&self) -> Result<Box<dyn Read>, FileError>;
}

pub trait FileSystem {
    fn entry(&self, path: &Path) -> Result<Box<dyn FileEntry>, FileError>;
    fn list_directory(&self, path: &Path) -> Result<Vec<Box<dyn FileEntry>>, FileError>;
    fn file_exists(&self, path: &Path) -> bool;
    fn directory_exists(&self, path: &Path) -> bool;
}
```

#### Directory Listing Domain

```rust
pub struct DirectoryListing {
    pub path: PathBuf,
    pub parent_path: Option<PathBuf>,
    pub entries: Vec<ListingEntry>,
    pub readme: Option<ReadmeContent>,
}

pub struct ListingEntry {
    pub name: String,
    pub path: PathBuf,
    pub kind: FileKind,
    pub size: Option<u64>,
    pub modified: Option<DateTime<Utc>>,
    pub is_hidden: bool,
}

pub trait ListingSorter {
    fn sort(&self, entries: &mut [ListingEntry]);
}

pub trait ListingFilter {
    fn filter(&self, entry: &ListingEntry) -> bool;
}
```

### Application Services

Coordinates between domain entities and ports to implement use cases.

```rust
pub struct DirectoryService<FS, Cache> 
where
    FS: FileSystem,
    Cache: CacheStore,
{
    file_system: FS,
    cache: Cache,
    config: DirectoryConfig,
}

impl<FS, Cache> DirectoryService<FS, Cache>
where
    FS: FileSystem,
    Cache: CacheStore,
{
    pub async fn list_directory(
        &self, 
        path: &Path,
        sort_method: SortMethod,
        reverse: bool,
    ) -> Result<DirectoryListing, ServiceError> {
        // Implementation
    }
    
    pub async fn get_file_info(
        &self,
        path: &Path,
    ) -> Result<FileInfo, ServiceError> {
        // Implementation
    }
}

pub struct ZipService<FS>
where
    FS: FileSystem,
{
    file_system: FS,
    config: ZipConfig,
}

impl<FS> ZipService<FS>
where
    FS: FileSystem,
{
    pub async fn generate_zip(
        &self,
        path: &Path,
    ) -> Result<impl Stream<Item = Result<Bytes, ZipError>>, ServiceError> {
        // Implementation
    }
}

pub struct SearchService<FS, Idx>
where
    FS: FileSystem,
    Idx: SearchIndex,
{
    file_system: FS,
    search_index: Idx,
    config: SearchConfig,
}

impl<FS, Idx> SearchService<FS, Idx>
where
    FS: FileSystem,
    Idx: SearchIndex,
{
    pub async fn search(
        &self,
        query: &str,
        path: &Path,
        options: SearchOptions,
    ) -> Result<SearchResults, ServiceError> {
        // Implementation
    }
}
```

### Primary Adapters (Driving)

Entry points to the application from the outside world.

#### Web API

```rust
pub struct WebApiServer {
    directory_service: Arc<dyn DirectoryServiceTrait>,
    zip_service: Arc<dyn ZipServiceTrait>,
    search_service: Arc<dyn SearchServiceTrait>,
    file_info_service: Arc<dyn FileInfoServiceTrait>,
    config: WebConfig,
}

impl WebApiServer {
    pub async fn run(self) -> Result<(), ServerError> {
        // Implementation
    }
    
    async fn handle_list_directory(
        &self,
        req: Request<Body>,
    ) -> Result<Response<Body>, Error> {
        // Implementation using directory_service
    }
    
    async fn handle_search(
        &self,
        req: Request<Body>,
    ) -> Result<Response<Body>, Error> {
        // Implementation using search_service
    }
    
    // Other handlers
}
```

#### CLI Interface

```rust
pub struct CliApp {
    directory_service: Arc<dyn DirectoryServiceTrait>,
    zip_service: Arc<dyn ZipServiceTrait>,
    search_service: Arc<dyn SearchServiceTrait>,
    config: CliConfig,
}

impl CliApp {
    pub fn run() -> Result<(), CliError> {
        // Implementation
    }
    
    fn handle_list_command(&self, path: &Path) -> Result<(), CliError> {
        // Implementation using directory_service
    }
    
    // Other command handlers
}
```

### Secondary Adapters (Driven)

Implementations of interfaces required by the application.

#### Native File System

```rust
pub struct NativeFileSystem {
    base_path: PathBuf,
}

impl FileSystem for NativeFileSystem {
    fn entry(&self, path: &Path) -> Result<Box<dyn FileEntry>, FileError> {
        // Implementation
    }
    
    fn list_directory(&self, path: &Path) -> Result<Vec<Box<dyn FileEntry>>, FileError> {
        // Implementation
    }
    
    // Other implementations
}

struct NativeFileEntry {
    path: PathBuf,
    metadata: std::fs::Metadata,
}

impl FileEntry for NativeFileEntry {
    // Implementation
}
```

#### Caching

```rust
pub struct MemoryCache {
    store: DashMap<String, (Vec<u8>, Instant)>,
    ttl: Duration,
}

impl CacheStore for MemoryCache {
    async fn get(&self, key: &str) -> Option<Vec<u8>> {
        // Implementation
    }
    
    async fn set(&self, key: &str, value: Vec<u8>) -> Result<(), CacheError> {
        // Implementation
    }
    
    async fn invalidate(&self, key: &str) -> Result<(), CacheError> {
        // Implementation
    }
}
```

## Cross-Cutting Concerns

### Configuration

```rust
pub struct AppConfig {
    pub filesystem: FilesystemConfig,
    pub directory: DirectoryConfig,
    pub search: SearchConfig,
    pub zip: ZipConfig,
    pub web: WebConfig,
    pub cli: CliConfig,
}

impl AppConfig {
    pub fn from_env() -> Result<Self, ConfigError> {
        // Implementation
    }
    
    pub fn from_file(path: &Path) -> Result<Self, ConfigError> {
        // Implementation
    }
}
```

### Logging

```rust
pub struct Logger {
    inner: tracing::Subscriber,
}

impl Logger {
    pub fn init(config: &LogConfig) -> Result<(), LogError> {
        // Implementation
    }
    
    pub fn debug<T: std::fmt::Display>(message: T) {
        // Implementation
    }
    
    pub fn info<T: std::fmt::Display>(message: T) {
        // Implementation
    }
    
    // Other log levels
}
```

### Error Handling

```rust
#[derive(Debug, thiserror::Error)]
pub enum AppError {
    #[error("File system error: {0}")]
    FileSystem(#[from] FileError),
    
    #[error("Service error: {0}")]
    Service(#[from] ServiceError),
    
    #[error("Configuration error: {0}")]
    Config(#[from] ConfigError),
    
    #[error("Cache error: {0}")]
    Cache(#[from] CacheError),
    
    // Other error types
}
```

## Testing Strategy

Each layer has specific testing approaches:

1. **Domain Layer**: Pure unit tests with no external dependencies
2. **Application Layer**: Unit tests with mocked dependencies
3. **Adapters**: Integration tests for primary adapters, unit tests with real dependencies for secondary adapters
4. **End-to-End**: Automated acceptance tests through the primary adapters

## Dependency Injection

```rust
pub struct AppContainer {
    pub file_system: Arc<dyn FileSystem>,
    pub cache_store: Arc<dyn CacheStore>,
    pub search_index: Arc<dyn SearchIndex>,
    pub directory_service: Arc<dyn DirectoryServiceTrait>,
    pub zip_service: Arc<dyn ZipServiceTrait>,
    pub search_service: Arc<dyn SearchServiceTrait>,
    pub file_info_service: Arc<dyn FileInfoServiceTrait>,
}

impl AppContainer {
    pub fn new(config: AppConfig) -> Self {
        // Construct and wire components
    }
}
```