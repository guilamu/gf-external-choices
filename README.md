# Gravity Forms External Choices

Populate Gravity Forms Multiple Choice fields from external CSV, JSON, or XLSX data sources.

## Manage External Data
- Populate choices from CSV files with auto-detection of delimiters and encoding
- Populate choices from JSON files (flat object arrays)
- Populate choices from XLSX (Excel) files using native PHP parsing
- Load data from external URLs or WordPress Media Library

## Caching & Performance
- Automatic caching with configurable refresh frequency (Hourly, Daily, Weekly)
- Manual cache refresh option
- Fallback to cached data if external source is unavailable

## Live Preview & Validation
- Live preview of choices within the Gravity Forms editor
- Status indicators for data health and connectivity
- Full validation to ensure data compatibility with Gravity Forms

## Key Features
- **CSV Support:** Auto-detects delimiters (comma, semicolon, tab) and encoding (UTF-8, ISO-8859-1)
- **JSON Support:** Supports flat object arrays with custom property mapping
- **XLSX Support:** Native PHP parsing using ZipArchive (no external libraries required)
- **Multilingual:** Works with content in any language
- **Translation-Ready:** All strings are internationalized
- **Secure:** Strict validation ensures only safe data is rendered
- **GitHub Updates:** Automatic updates from GitHub releases

## Requirements
- Gravity Forms (latest version recommended)
- WordPress 5.0 or higher
- PHP 7.4 or higher

## Installation
1. Upload the `gf-external-choices` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to any Form, add a Multiple Choice field (Dropdown, Radio, or Checkbox)
4. In the field settings, look for **External Choices** to configure your source

## FAQ
### What file formats are supported?
CSV, JSON, and XLSX (Excel) files are supported. The format is auto-detected based on file extension. Note: XLS (older Excel format) is not supported.

### What are the limits?
Files can be up to 10MB with a maximum of 1000 choices.

### Can I use nested JSON?
No, this version only supports flat object arrays. Nested objects are not supported.

### Why don't my choices show immediately after configuration?
The Gravity Forms editor loads choices from saved data. After configuring external choices, save the form and reload the page to see the updated choices in the editor.

## Project Structure
```
.
├── gf-external-choices.php       # Main plugin file
├── class-gf-external-choices.php # Main add-on class
├── readme.txt                    # WordPress.org readme
├── includes
│   ├── class-cache-manager.php   # Caching logic
│   ├── class-choice-validator.php # Data validation
│   ├── class-csv-parser.php      # CSV parsing
│   ├── class-data-fetcher.php    # Remote data fetching
│   ├── class-field-settings.php  # Admin UI settings
│   ├── class-github-updater.php  # GitHub auto-updates
│   ├── class-json-parser.php     # JSON parsing
│   └── class-xlsx-parser.php     # XLSX parsing
└── assets
    ├── css
    │   └── admin.css             # Admin styles
    └── js
        └── field-settings.js     # Admin scripts
```

## Changelog

### 1.0.0
- **New:** Initial release
- **New:** CSV support with auto-detection of delimiters and encoding
- **New:** JSON support for flat object arrays
- **New:** XLSX support using native PHP parsing
- **New:** URL and Media Library data sources
- **New:** Automatic caching with configurable refresh frequency

## License
This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>
