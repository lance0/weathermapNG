#!/bin/bash
# WeathermapNG Installation Test Suite
# Tests installation process in various scenarios

set -uo pipefail  # Remove -e to continue on errors (we handle them ourselves)

# Test configuration
TEST_DIR="/tmp/weathermapng-test-$$"
TEST_LOG="/tmp/weathermapng-test-$$.log"
FAILURES=0
SUCCESSES=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Test framework functions
test_start() {
    echo -e "${YELLOW}Testing: $1${NC}"
}

test_pass() {
    echo -e "${GREEN}✓ $1${NC}"
    ((SUCCESSES++))
}

test_fail() {
    echo -e "${RED}✗ $1${NC}"
    echo "  Error: $2"
    ((FAILURES++))
}

cleanup() {
    rm -rf "$TEST_DIR" 2>/dev/null || true
    rm -f "$TEST_LOG" 2>/dev/null || true
}

trap cleanup EXIT

# Test 1: Prerequisites check
test_prerequisites() {
    test_start "Prerequisites detection"
    
    # Test PHP version check
    if php -r "exit(version_compare(PHP_VERSION, '8.0.0', '>=') ? 0 : 1);"; then
        test_pass "PHP version check"
    else
        test_fail "PHP version check" "PHP 8.0+ required"
    fi
    
    # Test required commands
    for cmd in git composer; do
        if command -v "$cmd" &>/dev/null; then
            test_pass "Command $cmd found"
        else
            test_fail "Command $cmd" "Not found in PATH"
        fi
    done
    
    # MySQL client is optional (might use service container)
    if command -v mysql &>/dev/null; then
        test_pass "Command mysql found (optional)"
    else
        echo -e "${YELLOW}ℹ${NC} mysql client not found (optional - may use service container)"
    fi
    
    # Test PHP extensions
    for ext in gd json pdo mbstring; do
        if php -m 2>/dev/null | grep -q "^$ext$"; then
            test_pass "PHP extension $ext"
        else
            test_fail "PHP extension $ext" "Not loaded"
        fi
    done
}

# Test 2: Installation script syntax
test_script_syntax() {
    test_start "Installation script syntax"
    
    if bash -n install.sh 2>"$TEST_LOG"; then
        test_pass "install.sh syntax valid"
    else
        test_fail "install.sh syntax" "$(cat $TEST_LOG)"
    fi
    
    if php -l verify.php &>"$TEST_LOG"; then
        test_pass "verify.php syntax valid"
    else
        test_fail "verify.php syntax" "$(cat $TEST_LOG)"
    fi
}

# Test 3: Mock installation (dry run)
test_mock_installation() {
    test_start "Mock installation process"
    
    # Create mock LibreNMS structure
    mkdir -p "$TEST_DIR/librenms/html/plugins"
    mkdir -p "$TEST_DIR/librenms/bootstrap"
    touch "$TEST_DIR/librenms/bootstrap/app.php"
    
    # Set environment for test
    export LIBRENMS_PATH="$TEST_DIR/librenms"
    
    # Copy plugin files
    mkdir -p "$TEST_DIR/weathermapNG"
    cp -r . "$TEST_DIR/weathermapNG/" 2>/dev/null || true
    
    # Test directory creation
    cd "$TEST_DIR/librenms/html/plugins"
    if mkdir -p WeathermapNG/output; then
        test_pass "Directory creation"
    else
        test_fail "Directory creation" "Failed to create plugin directories"
    fi
    
    # Test permission setting (non-destructive)
    if chmod 755 WeathermapNG 2>/dev/null; then
        test_pass "Permission setting"
    else
        test_fail "Permission setting" "Failed to set permissions"
    fi
}

# Test 4: Verify script functionality
test_verify_script() {
    test_start "Verify script functionality"
    
    cd "$(dirname "$0")/.."
    
    # Test help option
    if php verify.php --help &>"$TEST_LOG"; then
        test_pass "verify.php --help"
    else
        test_fail "verify.php --help" "Script failed"
    fi
    
    # Test quiet mode
    if php verify.php --quiet &>"$TEST_LOG"; then
        if [ -s "$TEST_LOG" ]; then
            test_fail "Quiet mode" "Output produced in quiet mode"
        else
            test_pass "Quiet mode"
        fi
    else
        test_fail "verify.php --quiet" "Script failed"
    fi
}

# Test 5: Docker configuration
test_docker_config() {
    test_start "Docker configuration"
    
    if [ -f "docker-compose.simple.yml" ]; then
        if docker-compose -f docker-compose.simple.yml config &>"$TEST_LOG"; then
            test_pass "Docker Compose syntax"
        else
            test_fail "Docker Compose syntax" "Invalid configuration"
        fi
    else
        test_fail "Docker Compose file" "docker-compose.simple.yml not found"
    fi
    
    if [ -f ".env.docker" ]; then
        test_pass ".env.docker exists"
    else
        test_fail ".env.docker" "Template file missing"
    fi
}

# Test 6: Installation modes
test_installation_modes() {
    test_start "Installation mode detection"
    
    # Test help output
    if ./install.sh --help | grep -q "express"; then
        test_pass "Express mode documented"
    else
        test_fail "Express mode" "Not documented in help"
    fi
    
    if ./install.sh --help | grep -q "custom"; then
        test_pass "Custom mode documented"
    else
        test_fail "Custom mode" "Not documented in help"
    fi
    
    if ./install.sh --help | grep -q "docker"; then
        test_pass "Docker mode documented"
    else
        test_fail "Docker mode" "Not documented in help"
    fi
}

# Test 7: File integrity
test_file_integrity() {
    test_start "File integrity checks"
    
    local required_files=(
        "WeathermapNG.php"
        "composer.json"
        "routes.php"
        "plugin.json"
        "install.sh"
        "verify.php"
        "INSTALL.md"
        "README.md"
    )
    
    for file in "${required_files[@]}"; do
        if [ -f "$file" ]; then
            test_pass "$file exists"
        else
            test_fail "$file" "Required file missing"
        fi
    done
}

# Test 8: Composer validation
test_composer_validation() {
    test_start "Composer configuration"
    
    if composer validate --no-check-all &>"$TEST_LOG"; then
        test_pass "composer.json valid"
    else
        test_fail "composer.json" "Invalid configuration"
    fi
    
    if composer validate --no-check-publish &>"$TEST_LOG"; then
        test_pass "Composer package structure"
    else
        test_fail "Composer package" "Structure issues found"
    fi
}

# Main test execution
main() {
    echo "======================================"
    echo "WeathermapNG Installation Test Suite"
    echo "======================================"
    echo
    
    # Run all tests
    test_prerequisites
    test_script_syntax
    test_mock_installation
    test_verify_script
    test_docker_config
    test_installation_modes
    test_file_integrity
    test_composer_validation
    
    # Summary
    echo
    echo "======================================"
    echo "Test Results"
    echo "======================================"
    echo -e "${GREEN}Passed: $SUCCESSES${NC}"
    echo -e "${RED}Failed: $FAILURES${NC}"
    
    if [ $FAILURES -eq 0 ]; then
        echo -e "\n${GREEN}All tests passed! ✨${NC}"
        exit 0
    else
        echo -e "\n${RED}Some tests failed. Please review.${NC}"
        exit 1
    fi
}

# Run tests
main "$@"