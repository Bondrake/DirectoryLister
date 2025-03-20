<?php

declare(strict_types=1);

/**
 * DirectoryLister Performance Benchmark Tool
 * 
 * This tool measures performance metrics for the PHP implementation of DirectoryLister,
 * providing empirical data for comparison with the proposed Rust implementation.
 */

// Suppress warnings for benchmarking
error_reporting(E_ERROR | E_PARSE);

// Include Composer autoloader
require_once __DIR__ . '/../../../app/vendor/autoload.php';

use App\Bootstrap\BootManager;
use App\Controllers\DirectoryController;
use App\Controllers\FileInfoController;
use App\Controllers\SearchController;
use App\Controllers\ZipController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Benchmark class for running performance tests
 */
class Benchmark
{
    /** @var string The test to run */
    private string $test;
    
    /** @var string Size of the test (small, medium, large, huge) */
    private string $size;
    
    /** @var int Number of iterations to run */
    private int $iterations;
    
    /** @var bool Whether to create test files/directories */
    private bool $createTestData;
    
    /** @var string Path to test data directory */
    private string $testPath;
    
    /** @var array File size map for different test sizes */
    private array $sizeMap = [
        'small' => [
            'files' => 100,
            'max_size' => 1024 * 1024, // 1MB max file size
            'total_size' => 10 * 1024 * 1024, // 10MB total
            'max_depth' => 3
        ],
        'medium' => [
            'files' => 1000,
            'max_size' => 1024 * 1024, // 1MB max file size
            'total_size' => 100 * 1024 * 1024, // 100MB total
            'max_depth' => 5
        ],
        'large' => [
            'files' => 10000,
            'max_size' => 2 * 1024 * 1024, // 2MB max file size
            'total_size' => 1024 * 1024 * 1024, // 1GB total
            'max_depth' => 7
        ],
        'huge' => [
            'files' => 100000,
            'max_size' => 2 * 1024 * 1024, // 2MB max file size
            'total_size' => 10 * 1024 * 1024 * 1024, // 10GB total
            'max_depth' => 10
        ]
    ];
    
    /** @var array Results of benchmark tests */
    private array $results = [];
    
    /** @var array System information */
    private array $systemInfo = [];
    
    /** @var \DI\Container DI container */
    private $container;
    
    /**
     * Constructor
     * 
     * @param array $options Benchmark options
     */
    public function __construct(array $options = [])
    {
        $this->test = $options['test'] ?? 'all';
        $this->size = $options['size'] ?? 'medium';
        $this->iterations = (int)($options['iterations'] ?? 3);
        $this->createTestData = (bool)($options['create-test-data'] ?? false);
        $this->testPath = $options['test-path'] ?? __DIR__ . '/test-data';
        
        $this->collectSystemInfo();
        $this->initializeContainer();
        
        if ($this->createTestData) {
            $this->createTestDataStructure();
        }
    }
    
    /**
     * Run benchmarks
     */
    public function run(): array
    {
        $this->printHeader();
        
        switch ($this->test) {
            case 'directory-listing':
                $this->runDirectoryListingBenchmark();
                break;
            case 'file-hashing':
                $this->runFileHashingBenchmark();
                break;
            case 'zip-generation':
                $this->runZipGenerationBenchmark();
                break;
            case 'search':
                $this->runSearchBenchmark();
                break;
            case 'all':
                $this->runDirectoryListingBenchmark();
                $this->runFileHashingBenchmark();
                $this->runZipGenerationBenchmark();
                $this->runSearchBenchmark();
                break;
            default:
                echo "Unknown test: {$this->test}" . PHP_EOL;
                exit(1);
        }
        
        $this->printResults();
        
        return $this->results;
    }
    
    /**
     * Run directory listing benchmark
     */
    private function runDirectoryListingBenchmark(): void
    {
        echo "Running directory listing benchmark ({$this->size})..." . PHP_EOL;
        
        $times = [];
        $memoryUsage = [];
        
        $directoryController = $this->container->get(DirectoryController::class);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            // Clear cache and collect garbage
            $this->clearCaches();
            
            // Start measuring memory
            $memoryBefore = memory_get_usage(true);
            
            // Start timing
            $startTime = microtime(true);
            
            // Create mock request with the test path
            $request = $this->createMockRequest(['dir' => $this->testPath]);
            $response = $this->createMockResponse();
            
            // Execute directory listing
            $directoryController($request, $response);
            
            // Stop timing
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            // Measure memory usage
            $memoryAfter = memory_get_peak_usage(true);
            $memoryUsage[] = $memoryAfter - $memoryBefore;
            
            echo ".";
        }
        echo PHP_EOL;
        
        // Calculate results
        $this->results['directory-listing'] = $this->calculateMetrics($times, $memoryUsage);
    }
    
    /**
     * Run file hashing benchmark
     */
    private function runFileHashingBenchmark(): void
    {
        echo "Running file hashing benchmark ({$this->size})..." . PHP_EOL;
        
        $times = [];
        $memoryUsage = [];
        
        $fileInfoController = $this->container->get(FileInfoController::class);
        
        // Find some large files to hash
        $finder = new Finder();
        $files = $finder->files()->in($this->testPath)->sortBySize()->reverseSorting();
        $testFiles = [];
        
        foreach ($files as $file) {
            // Get top 5 largest files
            if (count($testFiles) >= 5) {
                break;
            }
            $testFiles[] = $file->getPathname();
        }
        
        if (empty($testFiles)) {
            echo "No test files found. Run with --create-test-data flag." . PHP_EOL;
            return;
        }
        
        foreach ($testFiles as $file) {
            $fileSize = filesize($file);
            echo "  Testing with file size: " . $this->formatBytes($fileSize) . PHP_EOL;
            
            for ($i = 0; $i < $this->iterations; $i++) {
                // Clear cache and collect garbage
                $this->clearCaches();
                
                // Start measuring memory
                $memoryBefore = memory_get_usage(true);
                
                // Start timing
                $startTime = microtime(true);
                
                // Create mock request with the test file
                $request = $this->createMockRequest(['info' => $file]);
                $response = $this->createMockResponse();
                
                // Execute file info (including hashing)
                $fileInfoController($request, $response);
                
                // Stop timing
                $endTime = microtime(true);
                $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
                
                // Measure memory usage
                $memoryAfter = memory_get_peak_usage(true);
                $memoryUsage[] = $memoryAfter - $memoryBefore;
                
                echo ".";
            }
            echo PHP_EOL;
        }
        
        // Calculate results
        $this->results['file-hashing'] = $this->calculateMetrics($times, $memoryUsage);
    }
    
    /**
     * Run ZIP generation benchmark
     */
    private function runZipGenerationBenchmark(): void
    {
        echo "Running ZIP generation benchmark ({$this->size})..." . PHP_EOL;
        
        $times = [];
        $memoryUsage = [];
        
        $zipController = $this->container->get(ZipController::class);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            // Clear cache and collect garbage
            $this->clearCaches();
            
            // Start measuring memory
            $memoryBefore = memory_get_usage(true);
            
            // Start timing
            $startTime = microtime(true);
            
            // Create mock request with the test path
            $request = $this->createMockRequest(['zip' => $this->testPath]);
            $response = $this->createMockResponse();
            
            // Execute ZIP generation
            $zipController($request, $response);
            
            // Stop timing
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            // Measure memory usage
            $memoryAfter = memory_get_peak_usage(true);
            $memoryUsage[] = $memoryAfter - $memoryBefore;
            
            echo ".";
        }
        echo PHP_EOL;
        
        // Calculate results
        $this->results['zip-generation'] = $this->calculateMetrics($times, $memoryUsage);
    }
    
    /**
     * Run search benchmark
     */
    private function runSearchBenchmark(): void
    {
        echo "Running search benchmark ({$this->size})..." . PHP_EOL;
        
        $times = [];
        $memoryUsage = [];
        
        $searchController = $this->container->get(SearchController::class);
        
        // Generate some search terms
        $searchTerms = ['test', 'file', 'data', 'benchmark', 'example'];
        
        foreach ($searchTerms as $term) {
            echo "  Searching for: '{$term}'" . PHP_EOL;
            
            for ($i = 0; $i < $this->iterations; $i++) {
                // Clear cache and collect garbage
                $this->clearCaches();
                
                // Start measuring memory
                $memoryBefore = memory_get_usage(true);
                
                // Start timing
                $startTime = microtime(true);
                
                // Create mock request with search term
                $request = $this->createMockRequest(['search' => $term]);
                $response = $this->createMockResponse();
                
                // Execute search
                $searchController($request, $response);
                
                // Stop timing
                $endTime = microtime(true);
                $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
                
                // Measure memory usage
                $memoryAfter = memory_get_peak_usage(true);
                $memoryUsage[] = $memoryAfter - $memoryBefore;
                
                echo ".";
            }
            echo PHP_EOL;
        }
        
        // Calculate results
        $this->results['search'] = $this->calculateMetrics($times, $memoryUsage);
    }
    
    /**
     * Calculate metrics from time and memory measurements
     */
    private function calculateMetrics(array $times, array $memoryUsage): array
    {
        // Calculate time statistics
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        
        // Calculate standard deviation
        $variance = array_sum(array_map(function ($x) use ($avgTime) {
            return pow($x - $avgTime, 2);
        }, $times)) / count($times);
        $stdDev = sqrt($variance);
        
        // Calculate memory statistics
        $avgMem = array_sum($memoryUsage) / count($memoryUsage);
        $peakMem = max($memoryUsage);
        
        return [
            'average_time_ms' => round($avgTime, 2),
            'min_time_ms' => round($minTime, 2),
            'max_time_ms' => round($maxTime, 2),
            'std_deviation_ms' => round($stdDev, 2),
            'average_memory_bytes' => round($avgMem, 2),
            'peak_memory_bytes' => round($peakMem, 2),
            'average_memory_mb' => round($avgMem / (1024 * 1024), 2),
            'peak_memory_mb' => round($peakMem / (1024 * 1024), 2),
            'test_size' => $this->size,
            'iterations' => $this->iterations
        ];
    }
    
    /**
     * Print benchmark results
     */
    private function printResults(): void
    {
        echo PHP_EOL . "=== BENCHMARK RESULTS ===" . PHP_EOL . PHP_EOL;
        
        foreach ($this->results as $test => $metrics) {
            echo "Test: {$test} ({$metrics['test_size']})" . PHP_EOL;
            echo "  Average time: {$metrics['average_time_ms']} ms" . PHP_EOL;
            echo "  Min time: {$metrics['min_time_ms']} ms" . PHP_EOL;
            echo "  Max time: {$metrics['max_time_ms']} ms" . PHP_EOL;
            echo "  Std deviation: {$metrics['std_deviation_ms']} ms" . PHP_EOL;
            echo "  Peak memory: {$metrics['peak_memory_mb']} MB" . PHP_EOL;
            echo PHP_EOL;
        }
        
        // Save results to JSON file
        $resultData = [
            'results' => $this->results,
            'system_info' => $this->systemInfo,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $jsonFilePath = __DIR__ . "/benchmark-results-{$this->size}-" . date('Ymd-His') . ".json";
        file_put_contents($jsonFilePath, json_encode($resultData, JSON_PRETTY_PRINT));
        
        echo "Results saved to: {$jsonFilePath}" . PHP_EOL;
    }
    
    /**
     * Print benchmark header
     */
    private function printHeader(): void
    {
        echo PHP_EOL;
        echo "=== DirectoryLister Performance Benchmark ===" . PHP_EOL;
        echo "Test: {$this->test}" . PHP_EOL;
        echo "Size: {$this->size}" . PHP_EOL;
        echo "Iterations: {$this->iterations}" . PHP_EOL;
        echo "System: {$this->systemInfo['os']} / PHP {$this->systemInfo['php_version']}" . PHP_EOL;
        echo PHP_EOL;
    }
    
    /**
     * Collect system information
     */
    private function collectSystemInfo(): void
    {
        $this->systemInfo = [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'timestamp' => time()
        ];
        
        // Try to get CPU info on Linux
        if (PHP_OS === 'Linux' && is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match('/model name.*: (.*)/m', $cpuinfo, $matches);
            if (isset($matches[1])) {
                $this->systemInfo['cpu'] = trim($matches[1]);
            }
            
            // Get number of processor cores
            preg_match_all('/processor/m', $cpuinfo, $matches);
            $this->systemInfo['cpu_cores'] = count($matches[0]);
        }
        
        // Try to get memory info on Linux
        if (PHP_OS === 'Linux' && is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/m', $meminfo, $matches);
            if (isset($matches[1])) {
                $totalMemKB = (int)$matches[1];
                $this->systemInfo['total_memory'] = $this->formatBytes($totalMemKB * 1024);
            }
        }
    }
    
    /**
     * Initialize the container
     */
    private function initializeContainer(): void
    {
        // Create container
        $this->container = BootManager::createContainer(
            __DIR__ . '/../../../app/config',
            __DIR__ . '/../../../app/cache'
        );
        
        // For compiled container, can't override services at runtime
        // Instead, we're using error_reporting to suppress warnings
    }
    
    /**
     * Create mock request object
     */
    private function createMockRequest(array $queryParams = []): \Slim\Psr7\Request
    {
        $serverParams = [
            'HTTP_HOST' => 'localhost:8080',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '8080',
            'REQUEST_URI' => '/' . (empty($queryParams) ? '' : '?' . http_build_query($queryParams)),
            'HTTPS' => 'off',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_METHOD' => 'GET',
        ];
        $cookies = [];
        $parsedBody = null;
        $uploadedFiles = [];
        
        $uri = new \Slim\Psr7\Uri('http', 'localhost', 8080, '/', http_build_query($queryParams));
        $headers = new \Slim\Psr7\Headers(['Host' => 'localhost:8080']);
        $body = new \Slim\Psr7\Stream(fopen('php://temp', 'r+'));
        
        return new \Slim\Psr7\Request('GET', $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles);
    }
    
    /**
     * Create mock response object
     */
    private function createMockResponse(): \Slim\Psr7\Response
    {
        return new \Slim\Psr7\Response();
    }
    
    /**
     * Clear caches and collect garbage
     */
    private function clearCaches(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        
        gc_collect_cycles();
    }
    
    /**
     * Create test data structure for benchmarking
     */
    private function createTestDataStructure(): void
    {
        echo "Creating test data structure in {$this->testPath}..." . PHP_EOL;
        
        // Create test directory if it doesn't exist
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
        
        // Get size configuration
        $config = $this->sizeMap[$this->size];
        $numFiles = $config['files'];
        $maxSize = $config['max_size'];
        $maxDepth = $config['max_depth'];
        
        // Create directory structure
        $dirs = [$this->testPath];
        
        for ($i = 0; $i < $maxDepth; $i++) {
            $newDirs = [];
            foreach ($dirs as $dir) {
                // Create 1-3 subdirectories per directory
                $numSubdirs = rand(1, 3);
                for ($j = 0; $j < $numSubdirs; $j++) {
                    $subdirName = $dir . '/dir_' . $i . '_' . $j;
                    if (!is_dir($subdirName)) {
                        mkdir($subdirName, 0777, true);
                    }
                    $newDirs[] = $subdirName;
                }
            }
            $dirs = array_merge($dirs, $newDirs);
        }
        
        // Create files
        $fileTypes = ['txt', 'md', 'json', 'xml', 'html', 'csv', 'js', 'css', 'php', 'yml'];
        $fileSizesSum = 0;
        
        for ($i = 0; $i < $numFiles; $i++) {
            // Choose random directory
            $dir = $dirs[array_rand($dirs)];
            
            // Choose random file type
            $fileType = $fileTypes[array_rand($fileTypes)];
            
            // Generate random file size, but ensure we don't exceed total size
            $remainingSize = $config['total_size'] - $fileSizesSum;
            if ($remainingSize <= 0) {
                break;
            }
            
            $fileSize = min(rand(1024, $maxSize), $remainingSize);
            $fileSizesSum += $fileSize;
            
            // Create file with random content
            $fileName = $dir . '/file_' . $i . '.' . $fileType;
            $this->createRandomFile($fileName, $fileSize);
            
            // Show progress
            if ($i % 100 === 0) {
                echo "Created {$i} files, total size: " . $this->formatBytes($fileSizesSum) . PHP_EOL;
            }
        }
        
        // Also create some README files
        $readmeContent = "# Test Directory\n\nThis is a test directory created for DirectoryLister benchmarking.";
        file_put_contents($this->testPath . '/README.md', $readmeContent);
        
        // Create some hidden files
        file_put_contents($this->testPath . '/.hidden_file', 'This is a hidden file');
        
        echo "Test data created: {$numFiles} files, total size: " . $this->formatBytes($fileSizesSum) . PHP_EOL;
    }
    
    /**
     * Create a file with random content
     */
    private function createRandomFile(string $fileName, int $size): void
    {
        $f = fopen($fileName, 'w');
        
        // Create a file with random content
        $chunkSize = 1024; // 1KB chunks
        $remaining = $size;
        
        while ($remaining > 0) {
            $writeSize = min($chunkSize, $remaining);
            $randomData = $this->getRandomData($writeSize);
            fwrite($f, $randomData);
            $remaining -= $writeSize;
        }
        
        fclose($f);
    }
    
    /**
     * Get random data
     */
    private function getRandomData(int $size): string
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+=-;:,./<>?";
        $data = '';
        
        // For larger sizes, repeat a chunk of data
        if ($size > 1024) {
            $chunk = '';
            for ($i = 0; $i < 1024; $i++) {
                $chunk .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            $copies = ceil($size / 1024);
            $data = str_repeat($chunk, $copies);
            return substr($data, 0, $size);
        }
        
        // For smaller sizes, generate completely random data
        for ($i = 0; $i < $size; $i++) {
            $data .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return $data;
    }
    
    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

/**
 * Parse command line options
 */
function parseCommandLineOptions(): array
{
    $options = [
        'test' => 'all',
        'size' => 'medium',
        'iterations' => 3,
        'create-test-data' => false,
        'test-path' => __DIR__ . '/test-data'
    ];
    
    $shortOptions = 't:s:i:c::';
    $longOptions = ['test:', 'size:', 'iterations:', 'create-test-data::', 'test-path:'];
    
    $args = getopt($shortOptions, $longOptions);
    
    if (isset($args['t'])) {
        $options['test'] = $args['t'];
    }
    if (isset($args['test'])) {
        $options['test'] = $args['test'];
    }
    
    if (isset($args['s'])) {
        $options['size'] = $args['s'];
    }
    if (isset($args['size'])) {
        $options['size'] = $args['size'];
    }
    
    if (isset($args['i'])) {
        $options['iterations'] = (int)$args['i'];
    }
    if (isset($args['iterations'])) {
        $options['iterations'] = (int)$args['iterations'];
    }
    
    if (isset($args['c']) || isset($args['create-test-data'])) {
        $options['create-test-data'] = true;
    }
    
    if (isset($args['test-path'])) {
        $options['test-path'] = $args['test-path'];
    }
    
    return $options;
}

/**
 * Print usage information
 */
function printUsage(): void
{
    echo "Usage: php benchmark.php [options]" . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  -t, --test=TEST            Test to run (directory-listing, file-hashing, zip-generation, search, all)" . PHP_EOL;
    echo "  -s, --size=SIZE            Test size (small, medium, large, huge)" . PHP_EOL;
    echo "  -i, --iterations=NUM       Number of iterations to run" . PHP_EOL;
    echo "  -c, --create-test-data     Create test data for benchmarking" . PHP_EOL;
    echo "  --test-path=PATH           Path to test data directory" . PHP_EOL;
    echo PHP_EOL;
}

// Parse command line options
$options = parseCommandLineOptions();

// Check if help is requested
if (isset($argv[1]) && ($argv[1] === '--help' || $argv[1] === '-h')) {
    printUsage();
    exit(0);
}

// Run benchmark
$benchmark = new Benchmark($options);
$results = $benchmark->run();

// Exit with success
exit(0);