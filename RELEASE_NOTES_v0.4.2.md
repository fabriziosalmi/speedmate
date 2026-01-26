# v0.4.2 - Security Fix

## 🔒 Security

### esbuild CORS Vulnerability Fix (GHSA-67mh-4wv8-2f99)

Fixed a **medium severity** security vulnerability in the esbuild development server that allowed malicious websites to send arbitrary requests and read responses due to overly permissive CORS settings.

**What was fixed:**
- Updated esbuild from 0.21.5 to 0.25.12
- Added npm overrides to enforce secure version despite VitePress dependency constraints
- Closes Dependabot security alert #1

**Attack Scenario Prevented:**
The vulnerability allowed attackers to:
1. Host a malicious web page
2. Use JavaScript to fetch resources from victim's local development server (localhost:8000)
3. Read source code, compiled bundles, and source maps
4. Exploit the `Access-Control-Allow-Origin: *` header

**Impact:**
This vulnerability only affected developers running the documentation development server (`npm run docs:dev`). Production deployments were never at risk.

**CVE Details:**
- **GHSA ID:** GHSA-67mh-4wv8-2f99
- **CVSS Score:** 5.3 (Medium)
- **CWE:** CWE-346 (Origin Validation Error)
- **Affected Versions:** esbuild <= 0.24.2
- **Fixed Version:** esbuild >= 0.25.0

---

## 📦 Installation

```bash
# WordPress plugin installation
wp plugin install https://github.com/fabriziosalmi/speedmate/archive/refs/tags/v0.4.2.zip --activate

# Or download and extract to wp-content/plugins/
wget https://github.com/fabriziosalmi/speedmate/archive/refs/tags/v0.4.2.zip
```

---

**Full Changelog**: https://github.com/fabriziosalmi/speedmate/compare/v0.4.1...v0.4.2
