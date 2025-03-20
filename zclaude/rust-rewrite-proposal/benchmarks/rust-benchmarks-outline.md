# Rust Implementation Benchmarks Outline

This document outlines the approach for implementing equivalent benchmarks in the Rust rewrite to ensure fair and accurate performance comparisons.

## Core Benchmark Structure

The Rust benchmarks should mirror the PHP benchmarks in methodology and measurement to ensure fair comparison. Here's how the Rust benchmark implementation should be structured:

```rust
// In crates/benchmarks/src/main.rs

use clap::Parser;
use std::path::PathBuf;
use std::time::Instant;
use directory_lister_core::*;
use directory_lister_application::*;
use directory_lister_infrastructure::*;

#[derive(Parser, Debug)]
#[clap(author, version, about)]
struct BenchmarkArgs {
    /// Test to run (directory-listing, file-hashing, zip-generation, search, all)
    #[clap(short, long, default_value = "all")]
    test: String,
    
    /// Test size (small, medium, large, huge)
    #[clap(short, long, default_value = "medium")]
    size: String,
    
    /// Number of iterations to run
    #[clap(short, long, default_value = "3")]
    iterations: usize,
    
    /// Create test data for benchmarking
    #[clap(short, long)]
    create_test_data: bool,
    
    /// Path to test data directory
    #[clap(long, default_value = "./test-data")]
    test_path: PathBuf,
}

struct Benchmark {
    args: BenchmarkArgs,
    results: serde_json::Value,
    system_info: serde_json::Value,
}

impl Benchmark {
    fn new(args: BenchmarkArgs) -> Self {
        let system_info = collect_system_info();
        
        let mut benchmark = Self {
            args,
            results: serde_json::json!({}),
            system_info,
        };
        
        if benchmark.args.create_test_data {
            benchmark.create_test_data_structure();
        }
        
        benchmark
    }
    
    fn run(&mut self) {
        println!("Running benchmarks...");
        
        match self.args.test.as_str() {
            "directory-listing" => self.run_directory_listing_benchmark(),
            "file-hashing" => self.run_file_hashing_benchmark(),
            "zip-generation" => self.run_zip_generation_benchmark(),
            "search" => self.run_search_benchmark(),
            "all" => {
                self.run_directory_listing_benchmark();
                self.run_file_hashing_benchmark();
                self.run_zip_generation_benchmark();
                self.run_search_benchmark();
            }
            _ => panic!("Unknown test: {}", self.args.test),
        }
        
        self.save_results();
    }
    
    fn run_directory_listing_benchmark(&mut self) {
        // Implementation
    }
    
    fn run_file_hashing_benchmark(&mut self) {
        // Implementation
    }
    
    fn run_zip_generation_benchmark(&mut self) {
        // Implementation
    }
    
    fn run_search_benchmark(&mut self) {
        // Implementation
    }
    
    fn create_test_data_structure(&self) {
        // Implementation
    }
    
    fn save_results(&self) {
        // Save results in the same JSON format as PHP benchmarks
    }
}

fn collect_system_info() -> serde_json::Value {
    // Collect system information (OS, Rust version, CPU, memory, etc.)
    serde_json::json!({
        "rust_version": rustc_version(),
        "os": std::env::consts::OS,
        // Additional system info
    })
}

fn main() {
    let args = BenchmarkArgs::parse();
    let mut benchmark = Benchmark::new(args);
    benchmark.run();
}
```

## Memory Usage Measurement

Measuring memory usage in Rust differs from PHP's approach. Here are the strategies:

1. **Simple Approach**: Use `std::process::Command` to run the benchmark in a child process and measure its peak memory usage with `ps` or `/proc/[pid]/status`.

2. **Advanced Approach**: Use a memory tracking allocator to accurately measure allocations:

```rust
// Custom allocator for memory tracking
use std::alloc::{GlobalAlloc, Layout, System};
use std::sync::atomic::{AtomicUsize, Ordering};

struct MemoryTracker;

static ALLOCATED: AtomicUsize = AtomicUsize::new(0);
static PEAK_ALLOCATED: AtomicUsize = AtomicUsize::new(0);

unsafe impl GlobalAlloc for MemoryTracker {
    unsafe fn alloc(&self, layout: Layout) -> *mut u8 {
        let size = layout.size();
        let new_allocated = ALLOCATED.fetch_add(size, Ordering::SeqCst) + size;
        
        // Update peak if current allocation is larger
        let mut peak = PEAK_ALLOCATED.load(Ordering::SeqCst);
        while new_allocated > peak {
            match PEAK_ALLOCATED.compare_exchange(peak, new_allocated, Ordering::SeqCst, Ordering::SeqCst) {
                Ok(_) => break,
                Err(current) => peak = current,
            }
        }
        
        System.alloc(layout)
    }

    unsafe fn dealloc(&self, ptr: *mut u8, layout: Layout) {
        ALLOCATED.fetch_sub(layout.size(), Ordering::SeqCst);
        System.dealloc(ptr, layout)
    }
}

// Register the allocator
#[global_allocator]
static ALLOCATOR: MemoryTracker = MemoryTracker;

// Get current and peak memory usage
fn get_memory_usage() -> (usize, usize) {
    let current = ALLOCATED.load(Ordering::SeqCst);
    let peak = PEAK_ALLOCATED.load(Ordering::SeqCst);
    (current, peak)
}

// Reset peak memory counter
fn reset_peak_memory() {
    PEAK_ALLOCATED.store(ALLOCATED.load(Ordering::SeqCst), Ordering::SeqCst);
}
```

## Additional Metrics to Consider

The Rust implementation can measure metrics that are harder to track in PHP:

1. **CPU Utilization**: Use `std::thread::sleep` and measure actual vs. wall clock time to estimate CPU usage.

2. **Thread Count**: Track the number of threads created during operations.

3. **I/O Operations**: Implement a wrapper around file system operations to count reads/writes.

4. **Concurrency Efficiency**: Measure how well operations scale with additional threads.

## Benchmark Execution Flow

For each benchmark:

1. Initialize the benchmark environment with appropriate services
2. Reset memory tracking
3. Measure and record start time
4. Execute the operation
5. Measure and record end time
6. Record peak memory usage
7. Calculate metrics (average, min, max, std deviation)
8. Save results in JSON format compatible with the PHP results

## Fair Comparison Guidelines

To ensure fair comparison between PHP and Rust implementations:

1. **Same Hardware**: Run all benchmarks on the same physical hardware
2. **Consistent Test Data**: Use identical test data structures
3. **Equivalent Functionality**: Ensure operations perform equivalent work
4. **Multiple Iterations**: Run multiple iterations to account for variance
5. **Quiet Environment**: Minimize background processes during testing
6. **Warm-up Runs**: Perform warm-up runs to prime any caches
7. **Proper Compilation Flags**: Use `--release` mode with appropriate optimizations

## Implementation Plan

1. Implement the benchmark framework in Rust
2. Implement test data generation that matches PHP's approach
3. Implement each benchmark operation
4. Add memory tracking
5. Generate results in compatible JSON format
6. Integrate with the benchmark runner script

This approach ensures that the benchmarks between PHP and Rust implementations are as comparable as possible, providing meaningful performance metrics.