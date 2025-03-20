# DirectoryLister Performance Benchmarks

This directory contains benchmarking utilities to measure the performance of both the PHP implementation (current) and the Rust implementation (future). These benchmarks provide empirical data for performance comparisons.

## Overview

The benchmarking suite tests the following key operations:

1. **Directory Listing** - Measuring the time to list directories of various sizes
2. **ZIP Generation** - Measuring the time to create ZIP archives of various sizes
3. **File Hashing** - Measuring the time to calculate file hashes (MD5, SHA1, SHA256)
4. **Search Operations** - Measuring the time to search through directory structures
5. **Memory Usage** - Measuring peak memory usage during operations

## Running Benchmarks

### PHP Implementation Benchmarks

```bash
# Basic usage
php benchmark.php --test=all

# Specific test
php benchmark.php --test=directory-listing

# With custom parameters
php benchmark.php --test=directory-listing --size=large --iterations=5
```

### Benchmark Sizes

Each benchmark supports different sizes:

- **small**: 100 files / 10MB total
- **medium**: 1,000 files / 100MB total
- **large**: 10,000 files / 1GB total
- **huge**: 100,000 files / 10GB total (optional)

## Output Format

Benchmarks output results in both human-readable format and JSON for further processing:

```json
{
  "test": "directory-listing",
  "size": "medium",
  "iterations": 5,
  "results": {
    "average_time_ms": 542,
    "min_time_ms": 498,
    "max_time_ms": 612,
    "std_deviation_ms": 42,
    "peak_memory_mb": 78.5
  },
  "system_info": {
    "php_version": "8.2.0",
    "os": "Linux",
    "cpu": "Intel(R) Core(TM) i7-9700K CPU @ 3.60GHz",
    "ram": "32GB"
  }
}
```

## Additional Metrics to Consider

Beyond raw execution time, the benchmarks measure:

1. **Memory Usage** - Peak and average memory consumption
2. **CPU Utilization** - Average CPU usage during operations
3. **I/O Operations** - Number of file system operations performed
4. **Scalability** - How performance scales with increasing data size
5. **Parallelism** - Effectiveness of concurrent operations
6. **Response Under Load** - Performance with multiple simultaneous requests

## Adding Custom Benchmarks

Custom benchmarks can be added by creating a new PHP file in the `benchmarks` directory that follows the benchmark interface pattern.

## Comparing with Rust Implementation

Once the Rust implementation is available, run equivalent benchmarks with:

```bash
cargo run --release --bin benchmark -- --test=all
```

Results will be output in the same format for direct comparison.