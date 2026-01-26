# SpeedMate - Attack Plan for v0.4.0+

## Executive Summary

Deep code analysis identified **80 issues** across the codebase:
- **12 Critical** (15%) - Security & Data Loss
- **22 High** (27.5%) - Performance & Stability  
- **31 Medium** (38.75%) - Code Quality
- **15 Low** (18.75%) - Nice to Have

**Status**: Production-ready but requires P0-P1 fixes for enterprise-grade quality.

---

## 🔴 P0 - CRITICAL (v0.3.3 - Security Hotfix)

**Target**: 1-2 days | **Impact**: Prevents potential security exploits

### Security Issues

1. **#79 - Enhanced Path Traversal Protection** - `StaticCache.php`
   - Add absolute path rejection
   - Add dot-file rejection  
   - Add comprehensive tests
   - **File**: `includes/Cache/StaticCache.php:296-304`
   - **Issue**: https://github.com/fabriziosalmi/speedmate/issues/NEW

2. **#78 - Strengthen API CSRF Protection** - `BatchEndpoints.php`
   - Remove referer-based check (easily spoofed)
   - Use WordPress nonce only
   - Add custom nonce for sensitive operations
   - **File**: `includes/API/BatchEndpoints.php:70-74`

3. **#80 - JSON Import Schema Validation** - `ImportExport.php`
   - Validate structure deeply
   - Check for malicious settings
   - Whitelist allowed keys
   - **File**: `includes/Admin/ImportExport.php`

### Security Testing

4. **#55 - Path Traversal Security Tests**
   - Test `../` attempts
   - Test absolute paths
   - Test null byte injection
   - Test URL-encoded traversal
   - **Create**: `tests/Integration/SecurityTest.php`

5. **#56 - File Upload Security Tests**
   - Test malicious uploads
   - Test MIME spoofing
   - Test size limits
   - **Add to**: `tests/Integration/ImportExportTest.php`

6. **#57 - Rate Limiter Security Tests**
   - Test window expiration
   - Test limit enforcement
   - Test key sanitization
   - **Create**: `tests/Integration/RateLimiterTest.php`

**Deliverable**: v0.3.3 - Security hardening release

---

## 🟠 P1 - HIGH (v0.4.0 - Architecture Refactor)

**Target**: 2-3 weeks | **Impact**: Performance, maintainability, scalability

### Phase 1: Architecture Cleanup (Week 1)

7. **#6 - Split StaticCache (God Object)**
   - Create `CacheStorage` - file operations
   - Create `CacheRules` - .htaccess/nginx
   - Create `CacheTTLManager` - TTL logic
   - Create `CacheMetadata` - metadata ops
   - Create `CachePolicy` - cacheable logic
   - **File**: `includes/Cache/StaticCache.php` (598 lines)
   - **Issue**: https://github.com/fabriziosalmi/speedmate/issues/14

8. **#7 - Split Admin Class**
   - Create `AdminMenu` - menu registration
   - Create `AdminSettings` - settings handling
   - Create `AdminRenderer` - UI rendering
   - Create `AdminBar` - admin bar
   - **File**: `includes/Admin/Admin.php` (413 lines)

9. **#25 - Create Singleton Trait**
   - Reduce duplication across 20+ classes
   - Add `__clone()` and `__wakeup()` protection
   - Standardize pattern
   - **Create**: `includes/Utils/Singleton.php`

10. **#4 - Dependency Injection Refactor**
    - Create `ServiceProvider`
    - Use constructor injection
    - Make dependencies explicit
    - **File**: `includes/Plugin.php:44-61`

### Phase 2: Performance Optimization (Week 2)

11. **#46 & #47 - Cache Admin Stats**
    - Cache size calculation → transient (5 min TTL)
    - Cached pages count → transient
    - Refresh on cache flush
    - **File**: `includes/Cache/StaticCache.php:391-427`
    - **Impact**: Reduces I/O on every admin page load

12. **#42 & #54 - Add Batch Limits**
    - Max 10 requests per batch
    - Add memory limit checks
    - Prevent DoS via batch API
    - **File**: `includes/API/BatchEndpoints.php`

13. **#43 - Fix N+1 in GarbageCollector**
    - Use bulk comment deletion
    - Batch meta operations
    - **File**: `includes/Utils/GarbageCollector.php:96`

14. **#45 - Fix Multisite N+1**
    - Use direct file ops instead of `switch_to_blog()`
    - Batch flush operations
    - **File**: `includes/Utils/Multisite.php`

15. **#72 - Fix Race Condition**
    - Use atomic transient operations
    - Implement proper locking
    - **File**: `includes/Cache/TrafficWarmer.php:90-94`

### Phase 3: Error Handling (Week 3)

16. **#28 - File Operation Error Handling**
    - Wrap `Filesystem::put_contents()` in try-catch
    - Log all failures
    - **File**: `includes/Cache/StaticCache.php`

17. **#29-32 - DOM & Image Error Handling**
    - Check `loadHTML()` return values
    - Handle `libxml_get_last_error()`
    - Add try-catch for image operations
    - **Files**: `BeastMode.php`, `AutoLCP.php`, `WebPConverter.php`

18. **#48-49 - Optimize DB Calls**
    - Cache table existence checks
    - Cache .htaccess validation
    - **File**: `includes/Utils/Stats.php:33-35`

**Deliverable**: v0.4.0 - Architecture & Performance Release

---

## 🟡 P2 - MEDIUM (v0.4.1 - Code Quality)

**Target**: 1-2 weeks | **Impact**: Maintainability, testing

### Phase 1: Code Quality

19. **#15-17 - Complete PHPDoc**
    - Add PHPDoc to all methods
    - Document all filters/actions
    - Add `@throws` annotations
    - **Tool**: `phpDocumentor` validation

20. **#20-24 - Extract Magic Numbers**
    - Create constants file
    - `SPEEDMATE_MENU_POSITION = 58`
    - `SPEEDMATE_DIR_PERMISSIONS = 0755`
    - `SPEEDMATE_LCP_RATE_LIMIT = 60`
    - `SPEEDMATE_MAX_IMPORT_SIZE = 1048576`
    - **Create**: `includes/constants.php`

21. **#26-27 - Reduce Duplication**
    - Create `Utils\Formatter::formatBytes()`
    - Create `Utils\Formatter::formatDuration()`
    - DRY principle

22. **#36-38 - Improve Logging**
    - Add logging to all error paths
    - Log purge operations
    - Log remote request failures
    - **Files**: Multiple

### Phase 2: Testing

23. **#61 - Multisite Integration Tests**
    - Create `tests/Integration/MultisiteTest.php`
    - Test network cache operations
    - Test site switching

24. **#62 - DynamicFragments Tests**
    - Create `tests/Integration/DynamicFragmentsTest.php`
    - Test fragment caching
    - Test replacement

25. **#63 - MediaOptimizer Tests**
    - Create `tests/Integration/MediaOptimizerTest.php`
    - Test image optimization
    - Test lazy loading

26. **#59-60 - Edge Case Tests**
    - Test rolling average math
    - Test TTL determination
    - Test negative values handling

**Deliverable**: v0.4.1 - Code Quality Release

---

## 🟢 P3 - LOW (v0.5.0 - Polish)

**Target**: 1 week | **Impact**: Standards compliance

27. **#11-14 - WPCS Compliance**
    - Convert tabs to 4 spaces
    - Run `phpcs --standard=WordPress`
    - Fix all warnings
    - **Tool**: PHP CodeSniffer

28. **#70-71 - Document Filters**
    - Update `docs/api/hooks.md`
    - List all filters with examples
    - Add hook usage guide

29. **#75-77 - Improve Cron**
    - Check if enabled before scheduling
    - Add cron health check
    - Add manual trigger option

30. **#50-51 - Minor Optimizations**
    - Optimize `sanitize_rules()`
    - Reduce array iterations

**Deliverable**: v0.5.0 - Polish & Documentation Release

---

## 📊 Release Timeline

### v0.3.3 (Security Hotfix) - 2 days
- P0 Critical security fixes
- Security test suite

### v0.4.0 (Architecture) - 3 weeks
- Refactor StaticCache & Admin
- Performance optimizations
- Error handling improvements

### v0.4.1 (Quality) - 2 weeks
- PHPDoc completion
- Magic number extraction
- Integration tests

### v0.5.0 (Polish) - 1 week
- WPCS compliance
- Documentation
- Cron improvements

**Total Timeline**: ~7 weeks to v0.5.0

---

## 🎯 Success Metrics

### Security
- ✅ 0 critical vulnerabilities
- ✅ 100% security test coverage
- ✅ OWASP compliance

### Performance
- ✅ <50ms admin page overhead
- ✅ <5 DB queries per request
- ✅ <10MB memory for batch ops

### Code Quality
- ✅ 100% PHPDoc coverage
- ✅ 0 WPCS violations
- ✅ >90% test coverage

### Maintainability
- ✅ Average class <300 lines
- ✅ Cyclomatic complexity <10
- ✅ 0 code duplication >10 lines

---

## 🔧 Tools & Automation

### Static Analysis
```bash
# PHPStan level 8
composer phpstan

# PHP CodeSniffer
composer phpcs

# PHPDoc validation
vendor/bin/phpdoc
```

### Testing
```bash
# Unit tests
composer test

# E2E tests
cd tests/e2e && npm test

# Coverage report
composer test -- --coverage-html coverage/
```

### CI/CD
- GitHub Actions for automated testing
- Dependabot for security updates
- Automated release tagging

---

## 📝 Notes

### Positive Findings ✅
- Strong type declarations
- Good null coalescing usage
- Security-conscious design
- Reasonable test coverage
- Modern PHP practices

### Architecture Decisions Needed
1. **DI Container**: Full commit or remove?
2. **Event System**: Add event dispatcher?
3. **Repository Pattern**: For database abstraction?
4. **Service Locator**: For dependency resolution?

### Breaking Changes
- v0.4.0 will break extensions using StaticCache directly
- Plan deprecation notices in v0.3.3
- Provide migration guide

---

## 🚀 Next Steps

1. ✅ Review this plan
2. Create GitHub issues for P0 items
3. Create GitHub milestone for v0.3.3
4. Start with #79 (path traversal hardening)
5. Write security tests (#55, #56, #57)
6. Release v0.3.3
7. Begin v0.4.0 refactoring

---

**Created**: 2026-01-26  
**Last Updated**: 2026-01-26  
**Status**: Draft - Awaiting Review
