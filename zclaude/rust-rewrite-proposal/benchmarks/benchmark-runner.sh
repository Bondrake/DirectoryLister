#!/bin/bash
# Benchmark Runner Script for DirectoryLister Performance Testing

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Default settings
SIZES=("small" "medium")
ITERATIONS=3
CREATE_TEST_DATA=false
TEST_PATH="./test-data"
TESTS=("directory-listing" "file-hashing" "zip-generation" "search")

# Print usage information
function print_usage {
    echo -e "${BLUE}DirectoryLister Benchmark Runner${NC}"
    echo
    echo "Usage: $0 [options]"
    echo
    echo "Options:"
    echo "  -s, --sizes       Sizes to test (small,medium,large,huge), comma-separated. Default: small,medium"
    echo "  -i, --iterations  Number of iterations for each test. Default: 3"
    echo "  -c, --create      Create test data before running benchmarks"
    echo "  -p, --path        Path to test data directory. Default: ./test-data"
    echo "  -t, --tests       Tests to run (directory-listing,file-hashing,zip-generation,search,all), comma-separated. Default: all"
    echo "  -h, --help        Display this help message"
    echo
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    key="$1"
    case $key in
        -s|--sizes)
        IFS=',' read -r -a SIZES <<< "$2"
        shift
        shift
        ;;
        -i|--iterations)
        ITERATIONS="$2"
        shift
        shift
        ;;
        -c|--create)
        CREATE_TEST_DATA=true
        shift
        ;;
        -p|--path)
        TEST_PATH="$2"
        shift
        shift
        ;;
        -t|--tests)
        if [[ "$2" == "all" ]]; then
            TESTS=("directory-listing" "file-hashing" "zip-generation" "search")
        else
            IFS=',' read -r -a TESTS <<< "$2"
        fi
        shift
        shift
        ;;
        -h|--help)
        print_usage
        exit 0
        ;;
        *)
        echo -e "${RED}Unknown option: $1${NC}"
        print_usage
        exit 1
        ;;
    esac
done

# Ensure the benchmark directory exists
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BENCHMARK="${SCRIPT_DIR}/benchmark.php"

# Check if benchmark script exists
if [[ ! -f "$PHP_BENCHMARK" ]]; then
    echo -e "${RED}Error: PHP benchmark script not found at $PHP_BENCHMARK${NC}"
    exit 1
fi

# Print configuration
echo -e "${BLUE}Benchmark Configuration:${NC}"
echo -e "  Sizes: ${YELLOW}${SIZES[*]}${NC}"
echo -e "  Iterations: ${YELLOW}$ITERATIONS${NC}"
echo -e "  Create Test Data: ${YELLOW}$CREATE_TEST_DATA${NC}"
echo -e "  Test Path: ${YELLOW}$TEST_PATH${NC}"
echo -e "  Tests: ${YELLOW}${TESTS[*]}${NC}"
echo

# Ensure test path is absolute
if [[ "$TEST_PATH" != /* ]]; then
    TEST_PATH="$SCRIPT_DIR/$TEST_PATH"
fi

# Create test data if requested
if [[ "$CREATE_TEST_DATA" == true ]]; then
    for size in "${SIZES[@]}"; do
        size_path="${TEST_PATH}-${size}"
        echo -e "${GREEN}Creating test data for size: $size at $size_path${NC}"
        php "$PHP_BENCHMARK" --test=all --size="$size" --iterations=1 --create-test-data --test-path="$size_path"
        echo
    done
fi

# Run benchmarks
for test in "${TESTS[@]}"; do
    echo -e "${BLUE}Running test: $test${NC}"
    
    for size in "${SIZES[@]}"; do
        size_path="${TEST_PATH}-${size}"
        echo -e "${GREEN}Testing size: $size${NC}"
        
        # Run PHP benchmark
        echo -e "${YELLOW}PHP Implementation:${NC}"
        php "$PHP_BENCHMARK" --test="$test" --size="$size" --iterations="$ITERATIONS" --test-path="$size_path"
        
        # Run Rust benchmark if available
        if [[ -f "${SCRIPT_DIR}/../target/release/benchmark" ]]; then
            echo -e "${YELLOW}Rust Implementation:${NC}"
            "${SCRIPT_DIR}/../target/release/benchmark" --test="$test" --size="$size" --iterations="$ITERATIONS" --test-path="$size_path"
        else
            echo -e "${RED}Rust benchmark executable not found. Skipping Rust tests.${NC}"
        fi
        
        echo
    done
done

# Generate visualization
if [[ -f "${SCRIPT_DIR}/results-visualization.php" ]]; then
    echo -e "${BLUE}Generating performance comparison visualization...${NC}"
    php "${SCRIPT_DIR}/results-visualization.php"
    
    # Open the visualization if on macOS
    if [[ "$OSTYPE" == "darwin"* ]] && [[ -f "${SCRIPT_DIR}/performance-comparison.html" ]]; then
        open "${SCRIPT_DIR}/performance-comparison.html"
    else
        echo -e "${GREEN}Visualization generated at:${NC} ${SCRIPT_DIR}/performance-comparison.html"
    fi
else
    echo -e "${YELLOW}Visualization script not found.${NC}"
fi

echo -e "${GREEN}Benchmarking complete!${NC}"