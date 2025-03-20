# DirectoryLister Rust Project Structure

This document outlines the directory structure and organization of the Rust rewrite, using a workspace-based approach to maximize code reuse and separation of concerns.

## Workspace Structure

```
directory-lister/
├── Cargo.toml              # Workspace definition
├── Cargo.lock              # Dependency lock file
├── crates/                 # All crates in the project
│   ├── core/               # Core domain models and interfaces
│   ├── application/        # Application services
│   ├── infrastructure/     # Infrastructure implementations
│   ├── web/                # Web API and interface
│   └── cli/                # Command line interface
├── docs/                   # Documentation
├── benches/                # Performance benchmarks
└── examples/               # Example configurations and usage
```

## Cargo Workspace

```toml
# ./Cargo.toml
[workspace]
members = [
    "crates/core",
    "crates/application",
    "crates/infrastructure",
    "crates/web",
    "crates/cli",
]

[workspace.dependencies]
tokio = { version = "1.32", features = ["full"] }
axum = "0.6"
tower = "0.4"
serde = { version = "1.0", features = ["derive"] }
serde_json = "1.0"
tracing = "0.1"
tracing-subscriber = "0.3"
config = "0.13"
zip = "0.6"
walkdir = "2.3"
thiserror = "1.0"
chrono = { version = "0.4", features = ["serde"] }
async-trait = "0.1"
bytes = "1.4"
futures = "0.3"
```

## Core Crate

```
crates/core/
├── Cargo.toml
└── src/
    ├── lib.rs              # Public exports
    ├── domain/             # Core domain entities and value objects
    │   ├── mod.rs
    │   ├── file_entry.rs   # File system item abstractions
    │   ├── file_kind.rs    # File type enums
    │   ├── metadata.rs     # File metadata traits
    │   └── sort.rs         # Sorting definitions
    ├── ports/              # Interface definitions (ports)
    │   ├── mod.rs
    │   ├── file_system.rs  # File system access port
    │   ├── search.rs       # Search functionality port
    │   ├── cache.rs        # Caching functionality port
    │   ├── config.rs       # Configuration port
    │   └── zip.rs          # ZIP creation port
    └── errors/             # Core error types
        ├── mod.rs
        └── error.rs        # Domain-specific errors
```

## Application Crate

```
crates/application/
├── Cargo.toml
└── src/
    ├── lib.rs              # Public exports
    ├── services/           # Application services
    │   ├── mod.rs
    │   ├── directory.rs    # Directory listing service
    │   ├── file_info.rs    # File information service
    │   ├── search.rs       # Search service 
    │   ├── zip.rs          # ZIP generation service
    │   └── markdown.rs     # Markdown rendering service
    ├── dto/                # Data transfer objects
    │   ├── mod.rs
    │   ├── directory.rs    # Directory listing DTOs
    │   ├── file.rs         # File information DTOs
    │   ├── search.rs       # Search result DTOs
    │   └── readme.rs       # README content DTOs
    └── errors/             # Application-specific errors
        ├── mod.rs
        └── error.rs        # Application error types
```

## Infrastructure Crate

```
crates/infrastructure/
├── Cargo.toml
└── src/
    ├── lib.rs                 # Public exports
    ├── adapters/              # Adapter implementations
    │   ├── mod.rs
    │   ├── file_system/       # File system adapters
    │   │   ├── mod.rs
    │   │   ├── native.rs      # Native FS implementation
    │   │   └── memory.rs      # In-memory FS for testing
    │   ├── cache/             # Cache adapters 
    │   │   ├── mod.rs
    │   │   ├── memory.rs      # In-memory cache
    │   │   ├── file.rs        # File-based cache
    │   │   └── redis.rs       # Redis cache implementation
    │   ├── search/            # Search adapters
    │   │   ├── mod.rs
    │   │   ├── in_memory.rs   # In-memory search
    │   │   └── tantivy.rs     # Full-text search with tantivy
    │   └── config/            # Configuration adapters
    │       ├── mod.rs
    │       ├── env.rs         # Environment variables config
    │       └── file.rs        # File-based configuration
    └── util/                  # Infrastructure utilities
        ├── mod.rs
        ├── compression.rs     # Compression utilities
        ├── hashing.rs         # File hashing utilities
        └── path.rs            # Path manipulation utilities
```

## Web Crate

```
crates/web/
├── Cargo.toml
├── static/                 # Static web assets
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   └── img/                # Images
└── src/
    ├── main.rs             # Web server entry point
    ├── lib.rs              # Library exports
    ├── api/                # API routes
    │   ├── mod.rs
    │   ├── directory.rs    # Directory listing endpoints
    │   ├── file.rs         # File access endpoints
    │   ├── search.rs       # Search endpoints
    │   └── zip.rs          # ZIP generation endpoints
    ├── handlers/           # Request handlers
    │   ├── mod.rs
    │   ├── directory.rs    # Directory listing handlers
    │   ├── file.rs         # File handlers
    │   ├── search.rs       # Search handlers
    │   └── error.rs        # Error handling
    ├── templates/          # HTML templates
    │   ├── mod.rs
    │   ├── layout.rs       # Layout templates
    │   ├── directory.rs    # Directory listing templates
    │   └── error.rs        # Error page templates
    ├── middleware/         # HTTP middleware
    │   ├── mod.rs
    │   ├── auth.rs         # Authentication (optional)
    │   ├── cache.rs        # HTTP caching
    │   └── logging.rs      # Request logging
    └── config/             # Web-specific configuration
        ├── mod.rs
        └── server.rs       # Server configuration
```

## CLI Crate

```
crates/cli/
├── Cargo.toml
└── src/
    ├── main.rs             # CLI entry point
    ├── commands/           # CLI commands
    │   ├── mod.rs
    │   ├── list.rs         # Directory listing command
    │   ├── search.rs       # Search command
    │   ├── zip.rs          # ZIP generation command
    │   └── info.rs         # File info command
    ├── ui/                 # Terminal UI components
    │   ├── mod.rs
    │   ├── directory.rs    # Directory listing UI
    │   ├── search.rs       # Search results UI
    │   └── progress.rs     # Progress indicators
    └── config/             # CLI-specific configuration
        ├── mod.rs
        └── cli.rs          # CLI configuration
```

## Benchmarks

```
benches/
├── directory_listing.rs    # Directory listing performance tests
├── search.rs               # Search performance tests
├── zip_generation.rs       # ZIP generation performance tests
└── file_hashing.rs         # File hashing performance tests
```

## Documentation

```
docs/
├── architecture/           # Architecture documentation
├── api/                    # API documentation
├── user-guide/             # User guide
└── developer-guide/        # Developer guide
```

## Examples

```
examples/
├── simple-server/          # Simple web server example
├── custom-theme/           # Custom theming example
└── cli-integration/        # CLI integration example
```

## Dependencies by Crate

### Core Crate

```toml
# crates/core/Cargo.toml
[dependencies]
serde = { workspace = true }
chrono = { workspace = true }
thiserror = { workspace = true }
async-trait = { workspace = true }
```

### Application Crate

```toml
# crates/application/Cargo.toml
[dependencies]
directory-lister-core = { path = "../core" }
serde = { workspace = true }
chrono = { workspace = true }
thiserror = { workspace = true }
async-trait = { workspace = true }
tokio = { workspace = true }
futures = { workspace = true }
bytes = { workspace = true }
tracing = { workspace = true }
```

### Infrastructure Crate

```toml
# crates/infrastructure/Cargo.toml
[dependencies]
directory-lister-core = { path = "../core" }
directory-lister-application = { path = "../application" }
serde = { workspace = true }
serde_json = { workspace = true }
tokio = { workspace = true }
chrono = { workspace = true }
walkdir = { workspace = true }
zip = { workspace = true }
config = { workspace = true }
tracing = { workspace = true }
thiserror = { workspace = true }
async-trait = { workspace = true }
bytes = { workspace = true }
futures = { workspace = true }
```

### Web Crate

```toml
# crates/web/Cargo.toml
[dependencies]
directory-lister-core = { path = "../core" }
directory-lister-application = { path = "../application" }
directory-lister-infrastructure = { path = "../infrastructure" }
tokio = { workspace = true }
axum = { workspace = true }
tower = { workspace = true }
tower-http = { version = "0.4", features = ["fs", "trace", "compression"] }
tracing = { workspace = true }
tracing-subscriber = { workspace = true }
serde = { workspace = true }
serde_json = { workspace = true }
config = { workspace = true }
askama = "0.12"
```

### CLI Crate

```toml
# crates/cli/Cargo.toml
[dependencies]
directory-lister-core = { path = "../core" }
directory-lister-application = { path = "../application" }
directory-lister-infrastructure = { path = "../infrastructure" }
tokio = { workspace = true }
clap = { version = "4.4", features = ["derive"] }
ratatui = "0.23"
crossterm = "0.27"
config = { workspace = true }
tracing = { workspace = true }
tracing-subscriber = { workspace = true }
```