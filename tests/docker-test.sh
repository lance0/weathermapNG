#!/bin/bash
# Docker Installation Test Suite
# Tests Docker-based installation and operation

set -euo pipefail

# Configuration
TEST_PREFIX="wmng-test"
TEST_PORT=18080
CLEANUP_ON_EXIT=true

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Test results
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Logging
log_test() {
    echo -e "${CYAN}[TEST]${NC} $1"
    ((TESTS_RUN++))
}

log_pass() {
    echo -e "${GREEN}  ✓${NC} $1"
    ((TESTS_PASSED++))
}

log_fail() {
    echo -e "${RED}  ✗${NC} $1"
    echo -e "${RED}    Error: $2${NC}"
    ((TESTS_FAILED++))
}

log_info() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

# Cleanup function
cleanup() {
    if [ "$CLEANUP_ON_EXIT" = true ]; then
        log_info "Cleaning up test containers..."
        docker-compose -p "$TEST_PREFIX" down -v 2>/dev/null || true
        docker network rm "${TEST_PREFIX}_default" 2>/dev/null || true
    fi
}

trap cleanup EXIT

# Test 1: Docker prerequisites
test_docker_prerequisites() {
    log_test "Docker prerequisites"
    
    if command -v docker &>/dev/null; then
        log_pass "Docker installed"
    else
        log_fail "Docker" "Not installed"
        return 1
    fi
    
    if docker info &>/dev/null; then
        log_pass "Docker daemon running"
    else
        log_fail "Docker daemon" "Not running or not accessible"
        return 1
    fi
    
    if command -v docker-compose &>/dev/null; then
        log_pass "Docker Compose installed"
    else
        log_fail "Docker Compose" "Not installed"
        return 1
    fi
}

# Test 2: Docker Compose configuration
test_compose_config() {
    log_test "Docker Compose configuration"
    
    if [ ! -f "docker-compose.simple.yml" ]; then
        log_fail "docker-compose.simple.yml" "File not found"
        return 1
    fi
    
    if docker-compose -f docker-compose.simple.yml config &>/dev/null; then
        log_pass "Compose file syntax valid"
    else
        log_fail "Compose file syntax" "Invalid YAML or configuration"
        return 1
    fi
    
    # Check required services
    local services=$(docker-compose -f docker-compose.simple.yml config --services 2>/dev/null)
    if echo "$services" | grep -q "librenms"; then
        log_pass "LibreNMS service defined"
    else
        log_fail "LibreNMS service" "Not found in compose file"
    fi
    
    if echo "$services" | grep -q "db"; then
        log_pass "Database service defined"
    else
        log_fail "Database service" "Not found in compose file"
    fi
}

# Test 3: Environment file
test_env_file() {
    log_test "Environment configuration"
    
    if [ ! -f ".env.docker" ]; then
        log_fail ".env.docker" "Template file not found"
        return 1
    fi
    
    # Create test environment file
    cp .env.docker .env.test
    
    # Check required variables
    local required_vars=(
        "DB_DATABASE"
        "DB_USERNAME"
        "DB_PASSWORD"
        "DB_ROOT_PASSWORD"
        "WEB_PORT"
    )
    
    for var in "${required_vars[@]}"; do
        if grep -q "^$var=" .env.docker; then
            log_pass "Environment variable $var defined"
        else
            log_fail "Environment variable $var" "Not defined"
        fi
    done
    
    rm -f .env.test
}

# Test 4: Container startup
test_container_startup() {
    log_test "Container startup and initialization"
    
    # Prepare test environment
    cp .env.docker .env
    sed -i.bak "s/WEB_PORT=.*/WEB_PORT=$TEST_PORT/" .env
    
    log_info "Starting containers (this may take a few minutes)..."
    
    if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml up -d &>/dev/null; then
        log_pass "Containers started"
    else
        log_fail "Container startup" "Failed to start containers"
        return 1
    fi
    
    # Wait for containers to be healthy
    log_info "Waiting for containers to be ready..."
    sleep 30
    
    # Check container status
    local containers=$(docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml ps -q)
    local all_running=true
    
    for container in $containers; do
        local status=$(docker inspect -f '{{.State.Status}}' "$container" 2>/dev/null)
        if [ "$status" = "running" ]; then
            log_pass "Container $container is running"
        else
            log_fail "Container $container" "Status: $status"
            all_running=false
        fi
    done
    
    if [ "$all_running" = false ]; then
        return 1
    fi
}

# Test 5: Service health checks
test_service_health() {
    log_test "Service health checks"
    
    # Test database connection
    if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml exec -T db mysqladmin ping &>/dev/null; then
        log_pass "Database is responsive"
    else
        log_fail "Database" "Not responding to ping"
    fi
    
    # Test web service
    if curl -sf "http://localhost:$TEST_PORT/login" &>/dev/null; then
        log_pass "Web service is accessible"
    else
        log_fail "Web service" "Not accessible on port $TEST_PORT"
    fi
    
    # Test plugin directory
    if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml exec -T librenms \
        test -d /opt/librenms/html/plugins/WeathermapNG; then
        log_pass "Plugin directory mounted"
    else
        log_fail "Plugin directory" "Not found in container"
    fi
}

# Test 6: Plugin installation in container
test_plugin_installation() {
    log_test "Plugin installation in container"
    
    # Check composer dependencies
    if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml exec -T librenms \
        test -f /opt/librenms/html/plugins/WeathermapNG/vendor/autoload.php; then
        log_pass "Composer dependencies installed"
    else
        log_info "Installing composer dependencies..."
        if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml exec -T librenms \
            bash -c "cd /opt/librenms/html/plugins/WeathermapNG && composer install --no-dev" &>/dev/null; then
            log_pass "Composer dependencies installed successfully"
        else
            log_fail "Composer dependencies" "Installation failed"
        fi
    fi
    
    # Check plugin files
    local plugin_files=(
        "WeathermapNG.php"
        "routes.php"
        "plugin.json"
    )
    
    for file in "${plugin_files[@]}"; do
        if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml exec -T librenms \
            test -f "/opt/librenms/html/plugins/WeathermapNG/$file"; then
            log_pass "Plugin file $file exists"
        else
            log_fail "Plugin file $file" "Not found"
        fi
    done
}

# Test 7: Container logs
test_container_logs() {
    log_test "Container logs analysis"
    
    # Check for errors in logs
    local error_count=$(docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml logs 2>&1 | \
        grep -ci "error\|fatal\|critical" || true)
    
    if [ "$error_count" -eq 0 ]; then
        log_pass "No critical errors in logs"
    else
        log_fail "Container logs" "$error_count error(s) found"
        log_info "Run 'docker-compose logs' to see details"
    fi
}

# Test 8: Cleanup test
test_cleanup() {
    log_test "Container cleanup"
    
    if docker-compose -p "$TEST_PREFIX" -f docker-compose.simple.yml down &>/dev/null; then
        log_pass "Containers stopped successfully"
    else
        log_fail "Container cleanup" "Failed to stop containers"
    fi
    
    # Check for orphaned resources
    local orphaned=$(docker ps -a --filter "name=$TEST_PREFIX" -q)
    if [ -z "$orphaned" ]; then
        log_pass "No orphaned containers"
    else
        log_fail "Orphaned containers" "Found: $orphaned"
    fi
}

# Main test execution
main() {
    echo "========================================"
    echo "WeathermapNG Docker Test Suite"
    echo "========================================"
    echo
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --no-cleanup)
                CLEANUP_ON_EXIT=false
                log_info "Cleanup disabled - containers will remain running"
                shift
                ;;
            --port)
                TEST_PORT="$2"
                shift 2
                ;;
            *)
                echo "Unknown option: $1"
                echo "Usage: $0 [--no-cleanup] [--port PORT]"
                exit 1
                ;;
        esac
    done
    
    # Run tests
    test_docker_prerequisites || true
    test_compose_config || true
    test_env_file || true
    
    # Only run container tests if Docker is available
    if command -v docker &>/dev/null && docker info &>/dev/null; then
        test_container_startup || true
        
        # Only test services if containers started
        if [ $? -eq 0 ]; then
            test_service_health || true
            test_plugin_installation || true
            test_container_logs || true
        fi
        
        test_cleanup || true
    else
        log_info "Skipping container tests - Docker not available"
    fi
    
    # Summary
    echo
    echo "========================================"
    echo "Test Summary"
    echo "========================================"
    echo -e "Tests run: $TESTS_RUN"
    echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
    echo -e "${RED}Failed: $TESTS_FAILED${NC}"
    
    if [ $TESTS_FAILED -eq 0 ]; then
        echo -e "\n${GREEN}All tests passed! 🎉${NC}"
        exit 0
    else
        echo -e "\n${YELLOW}Some tests failed. Review output above.${NC}"
        exit 1
    fi
}

# Run tests
main "$@"