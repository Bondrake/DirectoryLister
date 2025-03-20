# Additional Performance Metrics for DirectoryLister Comparison

Beyond the basic execution time and memory usage measurements, the following additional metrics should be considered for a comprehensive performance comparison between PHP and Rust implementations:

## 1. Concurrent User Simulation

### Importance
DirectoryLister is often deployed in multi-user environments where multiple people access the service simultaneously.

### Metrics to Collect
- **Requests per Second (RPS)**: Maximum sustainable throughput
- **Latency under Load**: Response time degradation as concurrent users increase
- **Resource Scaling**: How CPU and memory usage scale with concurrent users

### Implementation
```php
// PHP implementation using Apache Bench or wrk
$users = [1, 5, 10, 50, 100];
foreach ($users as $concurrentUsers) {
    exec("ab -n 1000 -c $concurrentUsers http://localhost:8080/?dir=test-data");
}
```

```rust
// Rust implementation using Tokio for concurrent requests
async fn benchmark_concurrent_users(
    users: &[usize], 
    endpoint: &str
) -> HashMap<usize, BenchmarkResult> {
    let mut results = HashMap::new();
    
    for &user_count in users {
        let mut handles = Vec::with_capacity(user_count);
        let start = Instant::now();
        
        for _ in 0..user_count {
            handles.push(tokio::spawn(async move {
                let client = reqwest::Client::new();
                let resp = client.get(endpoint).send().await?;
                resp.status().is_success()
            }));
        }
        
        let successes = futures::future::join_all(handles)
            .await
            .into_iter()
            .filter_map(Result::ok)
            .filter(|&success| success)
            .count();
            
        let duration = start.elapsed();
        
        results.insert(user_count, BenchmarkResult {
            duration,
            success_rate: successes as f64 / user_count as f64,
            // Other metrics
        });
    }
    
    results
}
```

## 2. Cold Start Performance

### Importance
First-request latency is critical for infrequently accessed directories or low-traffic deployments.

### Metrics to Collect
- **Cold Start Time**: Time to respond to the first request after initialization
- **Warm-up Effect**: Performance difference between first and subsequent requests
- **Initialization Overhead**: Memory and CPU cost during startup

### Implementation
```php
// PHP implementation
function measure_cold_start() {
    // Clear opcache and other caches
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Measure first request time
    $start = microtime(true);
    include_once "index.php"; // First load
    $cold_time = microtime(true) - $start;
    
    // Measure warm request time
    $start = microtime(true);
    include_once "index.php"; // Already loaded
    $warm_time = microtime(true) - $start;
    
    return [$cold_time, $warm_time];
}
```

```rust
// Rust implementation
fn measure_cold_start() -> (Duration, Duration) {
    // Cold start measurement
    let cold_start = Command::new("./directory_lister")
        .arg("--measure-startup")
        .output()
        .expect("Failed to execute command");
        
    let cold_duration = parse_duration_from_output(&cold_start.stdout);
    
    // Warm start measurement (server already running)
    let warm_start = Command::new("curl")
        .arg("http://localhost:8080/")
        .output()
        .expect("Failed to execute command");
        
    let warm_duration = parse_duration_from_output(&warm_start.stdout);
    
    (cold_duration, warm_duration)
}
```

## 3. I/O Efficiency

### Importance
File system operations are a core component of DirectoryLister's functionality.

### Metrics to Collect
- **File Operations Count**: Number of file open/read/close operations per request
- **Total I/O Bytes**: Amount of data read from disk
- **I/O Wait Time**: Time spent waiting for I/O operations
- **Cache Effectiveness**: Hit rate for file system caches

### Implementation
```php
// PHP implementation with I/O tracking
class IOTracker {
    private static $operations = 0;
    private static $bytes = 0;
    
    public static function track_open($file) {
        self::$operations++;
        return fopen($file, 'r');
    }
    
    public static function track_read($handle, $length) {
        $data = fread($handle, $length);
        self::$bytes += strlen($data);
        return $data;
    }
    
    // ... other tracked operations
    
    public static function get_stats() {
        return [
            'operations' => self::$operations,
            'bytes' => self::$bytes
        ];
    }
}
```

```rust
// Rust implementation with I/O tracking
struct IOStats {
    operations: AtomicUsize,
    bytes: AtomicUsize,
}

impl IOStats {
    fn track_operation(&self) {
        self.operations.fetch_add(1, Ordering::SeqCst);
    }
    
    fn track_bytes(&self, byte_count: usize) {
        self.bytes.fetch_add(byte_count, Ordering::SeqCst);
    }
}

// Wrap file system operations
struct TrackedFile {
    inner: std::fs::File,
    stats: Arc<IOStats>,
}

impl Read for TrackedFile {
    fn read(&mut self, buf: &mut [u8]) -> std::io::Result<usize> {
        self.stats.track_operation();
        let bytes_read = self.inner.read(buf)?;
        self.stats.track_bytes(bytes_read);
        Ok(bytes_read)
    }
}
```

## 4. Network Efficiency

### Importance
Network transfer efficiency affects overall user experience, especially with large directory listings.

### Metrics to Collect
- **Response Size**: Size of HTTP responses
- **Compression Ratio**: Effectiveness of response compression
- **Time to First Byte (TTFB)**: How quickly the server begins responding
- **Progressive Rendering**: Whether the client can begin rendering before receiving the full response

### Implementation
```php
// PHP implementation
function measure_network_metrics($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    return [
        'response_size' => strlen($body),
        'ttfb' => curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME),
        'total_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        'compressed' => strpos($header, 'Content-Encoding: gzip') !== false,
    ];
}
```

```rust
// Rust implementation
async fn measure_network_metrics(url: &str) -> NetworkMetrics {
    let client = reqwest::Client::new();
    let start = Instant::now();
    
    let response = client.get(url)
        .header("Accept-Encoding", "gzip")
        .send()
        .await?;
        
    let ttfb = start.elapsed();
    
    let content_encoding = response.headers()
        .get("content-encoding")
        .and_then(|h| h.to_str().ok())
        .unwrap_or("");
        
    let is_compressed = content_encoding.contains("gzip");
    let body = response.bytes().await?;
    let total_time = start.elapsed();
    
    NetworkMetrics {
        response_size: body.len(),
        ttfb,
        total_time,
        compressed: is_compressed,
    }
}
```

## 5. Resource Scaling

### Importance
Understanding how performance scales with directory size is crucial for large deployments.

### Metrics to Collect
- **Linear Scaling Factor**: How execution time increases with directory size
- **Memory Growth Factor**: How memory usage increases with directory size
- **Breaking Points**: Directory sizes where performance degrades non-linearly

### Implementation
```php
// PHP implementation
function test_scaling() {
    $sizes = [10, 100, 1000, 10000, 100000];
    $results = [];
    
    foreach ($sizes as $size) {
        create_test_directory($size);
        $result = run_benchmark("directory-listing", $size);
        $results[$size] = $result;
    }
    
    // Calculate scaling factors
    $scaling = [];
    for ($i = 1; $i < count($sizes); $i++) {
        $ratio = $results[$sizes[$i]]['average_time_ms'] / $results[$sizes[$i-1]]['average_time_ms'];
        $scaling[$sizes[$i]] = $ratio;
    }
    
    return $scaling;
}
```

```rust
// Rust implementation
async fn test_scaling() -> HashMap<usize, f64> {
    let sizes = [10, 100, 1000, 10000, 100000];
    let mut results = HashMap::new();
    let mut scaling = HashMap::new();
    
    for &size in &sizes {
        create_test_directory(size).await;
        let result = run_benchmark("directory-listing", size).await;
        results.insert(size, result);
    }
    
    // Calculate scaling factors
    for i in 1..sizes.len() {
        let prev_time = results[&sizes[i-1]].average_time.as_millis() as f64;
        let current_time = results[&sizes[i]].average_time.as_millis() as f64;
        let ratio = current_time / prev_time;
        scaling.insert(sizes[i], ratio);
    }
    
    scaling
}
```

## 6. Sustained Performance

### Importance
Some performance issues only appear during extended operation, especially with PHP's memory management.

### Metrics to Collect
- **Degradation Over Time**: Performance change after many requests
- **Memory Leaks**: Memory growth over repeated operations
- **CPU Utilization Over Time**: How CPU usage changes during sustained operation

### Implementation
```php
// PHP implementation
function test_sustained_performance($duration_seconds = 300) {
    $start_time = time();
    $request_count = 0;
    $timings = [];
    $memory_usage = [];
    
    while (time() - $start_time < $duration_seconds) {
        // Clear request-specific state but retain persistent state
        $_GET = ['dir' => 'test-data'];
        $_SERVER['REQUEST_URI'] = '/?dir=test-data';
        
        $mem_before = memory_get_usage(true);
        $req_start = microtime(true);
        
        // Simulate request
        include 'index.php';
        
        $req_time = microtime(true) - $req_start;
        $mem_after = memory_get_usage(true);
        
        $timings[] = $req_time;
        $memory_usage[] = $mem_after - $mem_before;
        $request_count++;
        
        // Short delay between requests
        usleep(100000); // 100ms
    }
    
    return [
        'request_count' => $request_count,
        'avg_time' => array_sum($timings) / count($timings),
        'time_trend' => calculate_trend($timings),
        'memory_trend' => calculate_trend($memory_usage),
    ];
}
```

```rust
// Rust implementation
async fn test_sustained_performance(duration: Duration) -> SustainedPerformanceMetrics {
    let start_time = Instant::now();
    let mut request_count = 0;
    let mut timings = Vec::new();
    let mut memory_usage = Vec::new();
    
    let server = start_test_server().await;
    let client = reqwest::Client::new();
    
    while start_time.elapsed() < duration {
        let mem_before = get_current_memory_usage();
        let req_start = Instant::now();
        
        // Simulate request
        let response = client.get("http://localhost:8080/?dir=test-data")
            .send()
            .await?;
            
        let req_time = req_start.elapsed();
        let mem_after = get_current_memory_usage();
        
        timings.push(req_time);
        memory_usage.push(mem_after - mem_before);
        request_count += 1;
        
        // Short delay between requests
        tokio::time::sleep(Duration::from_millis(100)).await;
    }
    
    SustainedPerformanceMetrics {
        request_count,
        avg_time: timings.iter().sum::<Duration>() / timings.len() as u32,
        time_trend: calculate_trend(&timings),
        memory_trend: calculate_trend(&memory_usage),
    }
}
```

## 7. Graceful Degradation

### Importance
How the system behaves under extreme conditions affects overall reliability.

### Metrics to Collect
- **Error Handling Time**: Time spent on error cases vs. success cases
- **Recovery Time**: How quickly the system recovers after errors
- **Resource Exhaustion Behavior**: System behavior when approaching resource limits

### Implementation
```php
// PHP implementation
function test_graceful_degradation() {
    // Test with invalid paths
    $invalid_path_time = benchmark_operation(function() {
        $_GET['dir'] = '/nonexistent/path';
        include 'index.php';
    });
    
    // Test with very long paths
    $long_path_time = benchmark_operation(function() {
        $_GET['dir'] = str_repeat('a/b/c/', 100);
        include 'index.php';
    });
    
    // Test with limited memory
    $original_limit = ini_get('memory_limit');
    ini_set('memory_limit', '32M');
    $limited_memory_time = benchmark_operation(function() {
        $_GET['dir'] = 'test-data-large';
        include 'index.php';
    });
    ini_set('memory_limit', $original_limit);
    
    return [
        'invalid_path_time' => $invalid_path_time,
        'long_path_time' => $long_path_time,
        'limited_memory_time' => $limited_memory_time,
    ];
}
```

```rust
// Rust implementation
async fn test_graceful_degradation() -> GracefulDegradationMetrics {
    let client = reqwest::Client::new();
    
    // Test with invalid paths
    let invalid_start = Instant::now();
    let invalid_response = client.get("http://localhost:8080/?dir=/nonexistent/path")
        .send()
        .await?;
    let invalid_path_time = invalid_start.elapsed();
    
    // Test with very long paths
    let long_path = format!("http://localhost:8080/?dir={}", "a/b/c/".repeat(100));
    let long_start = Instant::now();
    let long_response = client.get(&long_path)
        .send()
        .await?;
    let long_path_time = long_start.elapsed();
    
    // Test with resource limits
    // In Rust, we'd use a custom resource limiter
    let limited_response = client.get("http://localhost:8080/?dir=test-data-large")
        .timeout(Duration::from_secs(1))
        .send()
        .await;
    let limited_success = limited_response.is_ok();
    
    GracefulDegradationMetrics {
        invalid_path_time,
        long_path_time,
        resource_limited_success: limited_success,
    }
}
```

These additional metrics provide a more comprehensive view of the performance characteristics of both implementations, helping to identify strengths and weaknesses that might not be apparent from basic benchmarks alone.