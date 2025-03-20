<?php

declare(strict_types=1);

/**
 * Benchmark Results Visualization Tool
 * 
 * This script generates HTML visualizations of benchmark results for comparing
 * PHP and Rust implementations.
 */

// Function to load benchmark results from JSON files
function loadBenchmarkResults(string $directory): array
{
    $results = [];
    $files = glob($directory . '/benchmark-results-*.json');
    
    foreach ($files as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        
        if ($data) {
            // Extract implementation (PHP or Rust) from filename
            if (strpos($file, 'php') !== false) {
                $implementation = 'PHP';
            } elseif (strpos($file, 'rust') !== false) {
                $implementation = 'Rust';
            } else {
                $implementation = 'Unknown';
            }
            
            // Extract size from filename
            preg_match('/benchmark-results-(small|medium|large|huge)/', $file, $matches);
            $size = $matches[1] ?? 'unknown';
            
            // Add implementation and size info
            $data['implementation'] = $implementation;
            $data['size'] = $size;
            
            $results[] = $data;
        }
    }
    
    return $results;
}

// Function to generate performance comparison charts
function generateComparisonCharts(array $results): string
{
    $html = '<div class="charts-container">';
    
    // Group tests by type and size
    $grouped = [];
    foreach ($results as $result) {
        foreach ($result['results'] as $testName => $testData) {
            $size = $testData['test_size'];
            $implementation = $result['implementation'];
            
            if (!isset($grouped[$testName][$size])) {
                $grouped[$testName][$size] = [];
            }
            
            $grouped[$testName][$size][$implementation] = $testData;
        }
    }
    
    // Generate charts for each test type and size
    foreach ($grouped as $testName => $sizeData) {
        $html .= "<h2>Test: {$testName}</h2>";
        
        foreach ($sizeData as $size => $implData) {
            $html .= "<h3>Size: {$size}</h3>";
            
            // Time comparison chart
            $timeChartId = "time-chart-{$testName}-{$size}";
            $html .= "<div class='chart-wrapper'><canvas id='{$timeChartId}'></canvas></div>";
            
            $timeLabels = [];
            $timeData = [];
            $backgroundColor = [];
            
            foreach ($implData as $impl => $data) {
                $timeLabels[] = $impl;
                $timeData[] = $data['average_time_ms'];
                $backgroundColor[] = ($impl === 'PHP') ? 'rgba(54, 162, 235, 0.8)' : 'rgba(255, 99, 132, 0.8)';
            }
            
            $timeChartJs = "
                new Chart(document.getElementById('{$timeChartId}'), {
                    type: 'bar',
                    data: {
                        labels: " . json_encode($timeLabels) . ",
                        datasets: [{
                            label: 'Average Execution Time (ms)',
                            data: " . json_encode($timeData) . ",
                            backgroundColor: " . json_encode($backgroundColor) . "
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Execution Time Comparison'
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Time (ms)'
                                }
                            }
                        }
                    }
                });
            ";
            
            // Memory comparison chart
            $memoryChartId = "memory-chart-{$testName}-{$size}";
            $html .= "<div class='chart-wrapper'><canvas id='{$memoryChartId}'></canvas></div>";
            
            $memoryData = [];
            foreach ($implData as $impl => $data) {
                $memoryData[] = $data['peak_memory_mb'];
            }
            
            $memoryChartJs = "
                new Chart(document.getElementById('{$memoryChartId}'), {
                    type: 'bar',
                    data: {
                        labels: " . json_encode($timeLabels) . ",
                        datasets: [{
                            label: 'Peak Memory Usage (MB)',
                            data: " . json_encode($memoryData) . ",
                            backgroundColor: " . json_encode($backgroundColor) . "
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Memory Usage Comparison'
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Memory (MB)'
                                }
                            }
                        }
                    }
                });
            ";
            
            // Add the charts JavaScript
            $html .= "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    {$timeChartJs}
                    {$memoryChartJs}
                });
            </script>
            ";
            
            // Add detailed table
            $html .= "
            <table class='results-table'>
                <thead>
                    <tr>
                        <th>Implementation</th>
                        <th>Avg Time (ms)</th>
                        <th>Min Time (ms)</th>
                        <th>Max Time (ms)</th>
                        <th>Std Dev (ms)</th>
                        <th>Peak Memory (MB)</th>
                    </tr>
                </thead>
                <tbody>
            ";
            
            foreach ($implData as $impl => $data) {
                $html .= "
                    <tr>
                        <td>{$impl}</td>
                        <td>{$data['average_time_ms']}</td>
                        <td>{$data['min_time_ms']}</td>
                        <td>{$data['max_time_ms']}</td>
                        <td>{$data['std_deviation_ms']}</td>
                        <td>{$data['peak_memory_mb']}</td>
                    </tr>
                ";
            }
            
            $html .= "
                </tbody>
            </table>
            ";
            
            // Add improvement metrics if both PHP and Rust data are available
            if (isset($implData['PHP']) && isset($implData['Rust'])) {
                $timeImprovement = ($implData['PHP']['average_time_ms'] / $implData['Rust']['average_time_ms']);
                $memoryImprovement = ($implData['PHP']['peak_memory_mb'] / $implData['Rust']['peak_memory_mb']);
                
                $html .= "
                <div class='improvement-metrics'>
                    <h4>Improvement Metrics</h4>
                    <p>Execution Time: <strong>" . number_format($timeImprovement, 2) . "x</strong> faster</p>
                    <p>Memory Usage: <strong>" . number_format($memoryImprovement, 2) . "x</strong> more efficient</p>
                </div>
                ";
            }
        }
    }
    
    $html .= '</div>';
    return $html;
}

// Function to generate system information section
function generateSystemInfo(array $results): string
{
    $html = '<div class="system-info">';
    $html .= '<h2>System Information</h2>';
    
    // Group by implementation
    $phpInfo = null;
    $rustInfo = null;
    
    foreach ($results as $result) {
        if ($result['implementation'] === 'PHP' && !$phpInfo) {
            $phpInfo = $result['system_info'];
        } elseif ($result['implementation'] === 'Rust' && !$rustInfo) {
            $rustInfo = $result['system_info'];
        }
    }
    
    // PHP system info
    if ($phpInfo) {
        $html .= '<div class="info-section">';
        $html .= '<h3>PHP Environment</h3>';
        $html .= '<table class="info-table">';
        foreach ($phpInfo as $key => $value) {
            $html .= "<tr><th>{$key}</th><td>{$value}</td></tr>";
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    
    // Rust system info
    if ($rustInfo) {
        $html .= '<div class="info-section">';
        $html .= '<h3>Rust Environment</h3>';
        $html .= '<table class="info-table">';
        foreach ($rustInfo as $key => $value) {
            $html .= "<tr><th>{$key}</th><td>{$value}</td></tr>";
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

// Assemble the final HTML output
function generateHtml(array $results): string
{
    $charts = generateComparisonCharts($results);
    $systemInfo = generateSystemInfo($results);
    
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DirectoryLister Performance Comparison</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        h2 {
            margin-top: 40px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .charts-container {
            margin-bottom: 50px;
        }
        .chart-wrapper {
            margin: 20px 0;
            height: 300px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .results-table th, .results-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .results-table th {
            background-color: #f5f5f5;
        }
        .improvement-metrics {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .system-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 30px;
        }
        .info-section {
            flex: 0 0 48%;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table th, .info-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .info-table th {
            text-align: left;
            background-color: #f5f5f5;
            width: 40%;
        }
        @media (max-width: 768px) {
            .info-section {
                flex: 0 0 100%;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <h1>DirectoryLister Performance Comparison</h1>
    <p>This report compares the performance of PHP and Rust implementations of DirectoryLister across various operations and data sizes.</p>
    
    ' . $charts . '
    
    ' . $systemInfo . '
    
    <footer>
        <p>Generated on ' . date('Y-m-d H:i:s') . '</p>
    </footer>
</body>
</html>';
}

// Main execution
$resultsDir = __DIR__;
$results = loadBenchmarkResults($resultsDir);

if (empty($results)) {
    echo "No benchmark results found in {$resultsDir}\n";
    exit(1);
}

$html = generateHtml($results);
$outputFile = $resultsDir . '/performance-comparison.html';
file_put_contents($outputFile, $html);

echo "Performance comparison report generated: {$outputFile}\n";
exit(0);