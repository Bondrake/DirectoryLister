# DirectoryLister Rust Rewrite TODO

This document outlines actionable tasks to begin implementing the Rust rewrite of DirectoryLister. The tasks are organized by priority and phase to provide a clear path forward.

## Immediate Actions (Week 1-2)

### Project Setup

- [ ] Create GitHub repository for the Rust rewrite
- [ ] Set up Cargo workspace structure according to project-structure.md
- [ ] Configure CI/CD pipeline (GitHub Actions)
  - [ ] Rust build workflow
  - [ ] Test workflow
  - [ ] Linting and formatting checks
- [ ] Set up development environment
  - [ ] Install Rust toolchain
  - [ ] Configure IDE extensions
  - [ ] Set up debugging tools
- [ ] Create initial documentation
  - [ ] README.md with project overview
  - [ ] CONTRIBUTING.md with guidelines
  - [ ] CODE_OF_CONDUCT.md

### Core Domain Development

- [ ] Implement file_entry.rs with core traits
  - [ ] FileEntry trait
  - [ ] FileKind enum
  - [ ] FileMetadata struct
- [ ] Implement file_system.rs port interface
  - [ ] FileSystem trait
  - [ ] Directory operations
  - [ ] File operations
- [ ] Create error types in errors/error.rs
  - [ ] FileError enum
  - [ ] Result type aliases

## Phase 1 Tasks (Weeks 3-12)

### Domain Layer

- [ ] Complete domain models
  - [ ] HiddenRules implementation
  - [ ] SortMethod trait and implementations
  - [ ] ReadmeDetection functionality
  - [ ] Implement file type detection
- [ ] Develop port interfaces
  - [ ] CacheStore interface
  - [ ] SearchIndex interface
  - [ ] ConfigProvider interface
  - [ ] ZipGenerator interface

### Infrastructure Layer

- [ ] Implement native filesystem adapter
  - [ ] NativeFileSystem struct
  - [ ] NativeFileEntry struct
  - [ ] Path handling utilities
  - [ ] Security checks for path traversal
- [ ] Create in-memory filesystem for testing
  - [ ] MemoryFileSystem mock
  - [ ] Test utilities and fixtures
- [ ] Develop cache implementations
  - [ ] In-memory cache
  - [ ] File-based cache
  - [ ] Redis adapter (optional)
- [ ] Implement configuration loading
  - [ ] Environment-based config
  - [ ] File-based config
  - [ ] Default configurations

### Initial Testing

- [ ] Create unit tests for domain models
- [ ] Develop integration tests for infrastructure
- [ ] Set up benchmarking harness
  - [ ] File operation benchmarks
  - [ ] Directory listing benchmarks

## Phase 2 Tasks (Weeks 13-20)

### Application Services

- [ ] Directory listing service
  - [ ] List directory contents
  - [ ] Sort and filter entries
  - [ ] README detection and parsing
  - [ ] Pagination support
- [ ] File information service
  - [ ] Metadata extraction
  - [ ] File hash calculation
  - [ ] MIME type detection
- [ ] Search service
  - [ ] Basic file name search
  - [ ] Content search (optional)
  - [ ] Search result formatting
- [ ] ZIP generation service
  - [ ] Streaming ZIP creation
  - [ ] Progress reporting
  - [ ] Directory recursion
  - [ ] Compression options

### DTOs and Serialization

- [ ] Define data transfer objects
  - [ ] DirectoryListing struct
  - [ ] FileInfo struct
  - [ ] SearchResult struct
  - [ ] ReadmeContent struct
- [ ] Implement serialization with Serde
  - [ ] JSON serialization
  - [ ] Config serialization

## Phase 3 Tasks (Weeks 21-28)

### Web Interface

- [ ] Set up web server
  - [ ] Configure Axum/Tower
  - [ ] Set up middleware
  - [ ] Error handling
- [ ] Implement API endpoints
  - [ ] GET /api/directory endpoint
  - [ ] GET /api/file/:path endpoint
  - [ ] GET /api/search endpoint
  - [ ] GET /api/zip endpoint
- [ ] Create HTML templates
  - [ ] Layout template
  - [ ] Directory listing template
  - [ ] File info modal template
  - [ ] Error page templates
- [ ] Develop frontend
  - [ ] Port existing CSS to Rust project
  - [ ] Implement JavaScript functionality
  - [ ] Add theme switching

## Phase 4 Tasks (Weeks 29-32)

### CLI Interface

- [ ] Set up CLI framework with clap
  - [ ] Define command structure
  - [ ] Implement argument parsing
- [ ] Create terminal UI
  - [ ] Directory listing view
  - [ ] File info view
  - [ ] Search results view
- [ ] Implement CLI commands
  - [ ] list command
  - [ ] info command
  - [ ] search command
  - [ ] zip command

## Phase 5 Tasks (Weeks 33-40)

### Optimization and Refinement

- [ ] Performance profiling
  - [ ] Identify bottlenecks
  - [ ] Optimize critical paths
  - [ ] Memory usage optimization
- [ ] Security enhancements
  - [ ] Audit code for vulnerabilities
  - [ ] Implement rate limiting
  - [ ] Add optional authentication
- [ ] Documentation
  - [ ] API documentation
  - [ ] User guide
  - [ ] Developer guide
  - [ ] Example configurations
- [ ] Deployment preparation
  - [ ] Create Docker images
  - [ ] Prepare binary distributions
  - [ ] Write installation guide

## Release Tasks

- [ ] Version 0.1.0-alpha
  - [ ] Core functionality working
  - [ ] Basic web interface
  - [ ] Initial documentation
- [ ] Version 0.2.0-beta
  - [ ] All features implemented
  - [ ] Performance optimized
  - [ ] Complete documentation
- [ ] Version 1.0.0
  - [ ] Stable API
  - [ ] All bugs fixed
  - [ ] Full test coverage
  - [ ] Production ready

## Initial Development Focus

To make immediate progress, focus on these high-impact tasks:

1. Set up the project structure and build system
2. Implement the core domain models and interfaces
3. Create the native file system adapter
4. Develop a basic directory listing service
5. Set up a simple web server to test functionality

This will establish the foundation for the rewrite and validate the architectural approach early in the process.