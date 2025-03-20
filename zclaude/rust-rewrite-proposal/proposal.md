# DirectoryLister Rust Rewrite Proposal

## Executive Summary

This proposal outlines a plan to rewrite DirectoryLister in Rust, addressing performance bottlenecks and enhancing modularity. The rewrite will maintain core functionality while leveraging Rust's strengths in performance, safety, and concurrency. The new architecture will prioritize orthogonality, scalability, and maintainability.

## Motivation

### Current Limitations
- Performance bottlenecks with large directories and ZIP operations
- Memory usage concerns with large file operations
- Synchronous processing model limiting concurrency
- Framework coupling reducing code reusability

### Why Rust
- **Performance**: Zero-cost abstractions and efficient resource utilization
- **Safety**: Memory safety without garbage collection
- **Concurrency**: Safe parallel processing through ownership model
- **Orthogonality**: Better separation of concerns and modular design
- **Distribution**: Single binary deployment simplifying installation

## Architecture Overview

### Core Principles
1. **Orthogonal Design**: Clear separation between components
2. **Domain-Driven Design**: Aligning code structure with business domain
3. **Pure Functions**: Maximizing testability and minimizing side effects
4. **Trait-Based Abstractions**: Focusing on behavior over implementation
5. **SOLID Principles**: Particularly single responsibility and interface segregation

### High-Level Architecture

```
┌─────────────────────┐     ┌─────────────────────┐
│                     │     │                     │
│   Web Interface     │     │ Command Line        │
│                     │     │ Interface           │
│                     │     │                     │
└─────────┬───────────┘     └─────────┬───────────┘
          │                           │
          │                           │
          v                           v
┌─────────────────────────────────────────────────┐
│                                                 │
│               Application Layer                 │
│                                                 │
└─────────────────────┬───────────────────────────┘
                      │
                      v
┌─────────────────────────────────────────────────┐
│                                                 │
│                  Domain Layer                   │
│                                                 │
└─────────────────────┬───────────────────────────┘
                      │
                      v
┌─────────────────────────────────────────────────┐
│                                                 │
│             Infrastructure Layer                │
│                                                 │
└─────────────────────────────────────────────────┘
```

## Component Breakdown

### Core Domain (lib-core)
- File system abstractions and core domain models
- Pure domain logic independent of delivery mechanisms
- Trait-based interfaces for file operations, metadata extraction, and content handling

### Infrastructure (lib-infrastructure)
- File system access implementations
- Cache management
- Configuration loading
- Search indexing

### Application Services (lib-application)
- Use case implementations
- File listing, searching, and manipulation
- ZIP generation and streaming
- README parsing and rendering

### Web Interface (bin-web)
- REST API endpoints
- WebSocket notifications
- Static file serving
- HTML templating

### CLI Interface (bin-cli)
- Command-line interface
- Terminal UI capabilities
- Local file operations

## Technical Design

### Domain Layer

```rust
/// Core traits defining behavior
pub trait FileSystem {
    fn list_directory(&self, path: &Path) -> Result<Vec<FileEntry>>;
    fn file_metadata(&self, path: &Path) -> Result<FileMetadata>;
    fn file_content(&self, path: &Path) -> Result<Content>;
    // ...
}

/// Domain models
pub struct FileEntry {
    pub name: String,
    pub path: PathBuf,
    pub kind: FileKind,
    pub metadata: FileMetadata,
}

pub enum FileKind {
    File,
    Directory,
    Symlink,
}

pub struct FileMetadata {
    pub size: u64,
    pub modified: DateTime<Utc>,
    pub accessed: DateTime<Utc>,
    pub created: DateTime<Utc>,
    pub permissions: Permissions,
}
```

### Application Services

```rust
pub struct DirectoryService<FS: FileSystem> {
    file_system: FS,
    config: ServiceConfig,
}

impl<FS: FileSystem> DirectoryService<FS> {
    pub fn list_directory(&self, path: &Path) -> Result<DirectoryListing> {
        // Implementation using file_system
    }
    
    pub fn search_files(&self, query: &str) -> Result<SearchResults> {
        // Implementation using file_system
    }
    
    pub fn generate_zip(&self, path: &Path) -> Result<impl Stream<Item = Bytes>> {
        // Implementation using file_system
    }
}
```

### Web Interface

```rust
async fn list_directory(
    Path(path): Path<String>,
    State(state): State<AppState>,
) -> impl IntoResponse {
    let dir_service = &state.directory_service;
    match dir_service.list_directory(&PathBuf::from(path)).await {
        Ok(listing) => Json(listing).into_response(),
        Err(e) => (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()).into_response(),
    }
}
```

## Technology Choices

### Core Libraries
- **Web Framework**: Axum (Tower/Tokio ecosystem)
- **Templating**: Askama (compile-time templates)
- **CLI**: Clap + Ratatui
- **Serialization**: Serde
- **Async Runtime**: Tokio
- **Database**: SQLite via Rusqlite (for search indexing)
- **Compression**: zstd and zip-rs
- **Markdown**: pulldown-cmark
- **Configuration**: config-rs
- **Logging**: tracing

### Build & Deployment
- **Build System**: Cargo workspaces
- **CI/CD**: GitHub Actions
- **Containerization**: Multi-stage Docker builds
- **Release Packaging**: Binary distributions for major platforms

## Implementation Plan

### Phase 1: Core Domain & Infrastructure (3 months)
- Develop file system abstraction and core domain models
- Implement basic file operations and metadata extraction
- Create caching layer and configuration management
- Develop search functionality

### Phase 2: Application Services (2 months)
- Directory listing services
- Search services
- ZIP generation services
- File information services

### Phase 3: Web Interface (2 months)
- REST API endpoints
- HTML templates
- Static file handling
- JavaScript integration

### Phase 4: CLI Interface (1 month)
- Command-line argument parsing
- Terminal UI for directory navigation
- Integration with application services

### Phase 5: Refinement & Performance (2 months)
- Performance optimization
- Security hardening
- Internationalization
- Documentation

## Performance Expectations

| Operation | PHP (Current) | Rust (Projected) | Improvement |
|-----------|---------------|------------------|-------------|
| Directory Listing (10k files) | 1200ms | 150ms | 8x |
| Search (10k files) | 2500ms | 200ms | 12.5x |
| ZIP Generation (1GB) | 45s | 5s | 9x |
| File Hashing (100MB) | 800ms | 50ms | 16x |
| Memory Usage (Peak) | 120MB | 15MB | 8x reduction |

## Security Improvements
- Memory safety guarantees eliminating buffer overflows
- Type-safe handling of user inputs
- Improved permission checking with capability-based security
- Protection against path traversal attacks

## User Experience Enhancements
- Faster response times for all operations
- Progressive ZIP streaming with progress reporting
- Real-time directory updates via WebSockets
- Offline capabilities through PWA support

## Conclusion

The Rust rewrite offers a significant opportunity to address current limitations while enhancing performance, security, and maintainability. Through orthogonal design and modern architecture principles, the new system will provide a solid foundation for future features while delivering immediate benefits to users.