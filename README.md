# Vite-FlightPHP

A modern PHP web application skeleton that combines the FlightPHP microframework with Vite for frontend asset management. This boilerplate provides a solid foundation for building web applications with PHP on the backend and modern frontend development tools.

![Build Status](https://img.shields.io/badge/build-passing-brightgreen) ![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)![Node Version](https://img.shields.io/badge/node-%3E%3D18.0-green)

## Features

- **FlightPHP 3.x** - Lightweight PHP microframework with minimal footprint
- **Vite 7.x** - Next-generation frontend tooling for fast development and build times
- **Tailwind CSS 4.x** - Utility-first CSS framework for rapid UI development
- **Alpine.js 3.x** - Lightweight JavaScript framework for reactive UI components
- **Hot Module Replacement (HMR)** - Instant updates during development without page reload
- **Dark Mode Support** - Built-in dark/light theme toggle system
- **PSR-12 Compliant** - Follows PHP coding standards for consistency
- **Biome.js** - All-in-one JavaScript formatter, linter, and bundler
- **Responsive Design** - Mobile-first approach for optimal user experience

## Prerequisites

- **PHP 8.0 or higher** - Make sure you have PHP installed and available
- **Composer** - PHP dependency manager
- **Node.js 18.x or higher** - JavaScript runtime environment

## Quick Start

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd vite-flightphp
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install frontend dependencies:
   ```bash
   cd vite
   npm install
   ```

4. Start the development servers:
   - In one terminal, start the PHP development server from the project root:
     ```bash
     composer start
     # or
     php -S localhost:8000 -t public
     ```
   - In another terminal, start the Vite development server:
     ```bash
     cd vite && npm run dev
     ```

5. Visit `http://localhost:8000` in your browser.

## Development Workflow

### Understanding the Vite-FlightPHP Integration

This project provides a seamless integration between FlightPHP (backend) and Vite (frontend). Here's how it works:

**Development Mode:**
- Vite serves frontend assets from `localhost:5173`
- FlightPHP proxies asset requests to Vite
- Hot Module Replacement (HMR) provides instant feedback during development

**Production Mode:**
- Vite builds optimized assets to `public/dist/`
- Assets are served directly from FlightPHP
- Asset manifest ensures proper cache-busting

### Security Headers and Middleware

The application includes a Security Headers middleware (`app/middlewares/SecurityHeadersMiddleware.php`) that automatically applies security best practices:

- **Content Security Policy (CSP)** - Dynamically built with nonces for script execution
- **X-Frame-Options** - Prevents clickjacking by restricting iframe usage
- **X-XSS-Protection** - Enables browser XSS filtering
- **X-Content-Type-Options** - Prevents MIME-type sniffing
- **Strict-Transport-Security** - Enforces HTTPS connections
- **Referrer-Policy** - Controls referrer information
- **Permissions-Policy** - Restricts browser feature access

The middleware is automatically applied to all routes via the router configuration in `app/config/routes.php`.

### Asset Management

The asset system uses a custom Vite controller (`app/controllers/Vite.php`) with two key functions:

#### `entry()` Function
Incorporates your main JavaScript entry points into PHP views. The main entry point file (e.g., `main.js`) must be located in the `vite/src/` directory:

```php
<?php echo vite()->entry('main.js'); ?>
```

#### `asset()` Function
Provides a convenient way to reference static assets like images, fonts, or other files:

```php
<!-- Image asset -->
<img src="<?php echo vite()->asset('/assets/logo.png'); ?>" alt="Logo">
```

**Important:** All static assets must be placed in the `vite/src/` directory. During development, assets are served from the Vite dev server, and in production, they are processed and optimized by Vite and placed in the `public/dist/` directory.

The `asset()` function automatically handles:
- Development server URLs (Vite dev server)
- Production asset paths (built assets)
- Cache-busting via manifest files
- Fallback handling for missing assets

## Project Structure

```
vite-flightphp/
├── app/                          # PHP application core
│   ├── config/                   # Configuration files
│   │   ├── bootstrap.php        # Application bootstrap
│   │   ├── config.php           # Main application configuration
│   │   ├── config_sample.php    # Sample configuration file
│   │   ├── routes.php           # Route definitions
│   │   └── services.php         # Service container definitions
│   ├── controllers/              # Request controllers
│   │   ├── ApiExampleController.php  # Example API controller
│   │   └── Vite.php             # Vite asset controller
│   ├── middlewares/              # Middleware implementations
│   │   └── SecurityHeadersMiddleware.php  # Security headers implementation
│   └── views/                    # PHP view templates
│       └── welcome.php           # Welcome page view
├── public/                       # Web root directory
│   ├── index.php                 # Frontend controller (entry point)
│   └── dist/                     # Production Vite assets (built on deploy)
├── vendor/                       # PHP dependencies (via Composer)
├── vite/                         # Frontend development environment
│   ├── src/                      # Frontend source files
│   │   ├── main.js               # Main JS entry point
│   │   ├── assets/               # Static assets (images, etc.)
│   │   ├── components/           # Frontend components
│   │   │   ├── counter.js       # Counter component
│   │   │   └── images.js        # Image handling
│   │   ├── styles/               # CSS styles
│   │   │   └── base.css         # Base styles with Tailwind
│   ├── package.json              # Frontend dependencies
│   └── vite.config.js            # Vite build configuration
├── composer.json                 # PHP dependencies
├── README.md                     # Project documentation
├── .gitignore                    # Git ignore rules
├── biome.json                    # Biome.js configuration
└── ruleset.xml                   # PHP CodeSniffer ruleset
```

## Configuration

### PHP Configuration
- **app/config/config.php** - Main application configuration (create from config_sample.php)
- **app/config/routes.php** - URL routing definitions with middleware support
- **app/config/services.php** - Service container definitions and dependency injection setup
- **app/config/bootstrap.php** - Application bootstrap process and initialization

### Key Configuration Variables
The following variables in `app/config/config.php` are essential for the Vite integration:

- **vite_host** - Defines the Vite development server host and port (default: `'localhost:5173'`)
- **production** - Boolean flag indicating production mode (default: `false`)
- **csp_nonce** - Automatically generated Content Security Policy nonce for security
- **flight.base_url** - Base URL for your application
- **flight.views.path** - Path to view templates

### Frontend Configuration
- **vite/vite.config.js** - Vite build configuration including development server, build output, and live reload settings
- **vite/src/styles/base.css** - Tailwind CSS configuration with custom dark mode variant
- **biome.json** - JavaScript/TypeScript formatting and linting rules

### Creating Configuration Files
After installation, you need to create the config.php file from the sample:

```bash
cp app/config/config_sample.php app/config/config.php
```

Then modify the values in config.php according to your environment needs.


## Development Commands

### PHP Commands
- `composer start` - Start PHP development server
- `composer install` - Install PHP dependencies
- `composer update` - Update PHP dependencies
- `composer lint` - Run PSR-12 code linting

### Frontend Commands (from `vite/` directory)
- `npm run dev` - Start Vite development server with HMR
- `npm run build` - Build assets for production
- `npm run preview` - Preview production build locally
- `npm run lint` - Run Biome.js linter
- `npm run format` - Format code with Biome.js

## API Development

### Available API Endpoints
The skeleton includes example RESTful API endpoints:

```
GET /api/users          - Retrieve all users (example data)
GET /api/users/{id}     - Retrieve specific user (example data)
POST /api/users/{id}    - Update existing user (example data)
```

Note: The API currently uses example data arrays. In a real application, you would connect these endpoints to a database or other data source.

## Deployment

### Production Build
```bash
# Build production assets
cd vite && npm run build

# Run linting
composer lint

# Generate optimized autoloader (for production)
composer dump-autoload --optimize

# Clear any caches
composer clear-cache
```

### Server Requirements
- **PHP 8.0+** with required extensions:
  - PHP OpenSSL extension
  - PHP JSON extension
- **Web Server**: Apache or Nginx
- **File Permissions**: Write access to `public/uploads/`, `public/dist/`

### Environment Setup for Production
```bash
# Production environment
composer install --no-dev
cd vite && npm ci --omit=dev && npm run build

# Set proper file permissions
chmod -R 755 storage
chmod -R 755 public/uploads
```

## Contributing

We welcome contributions! Please follow these guidelines:

### Development Process
1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/amazing-feature`
3. **Make** your changes following the project's code style
4. **Test** your changes thoroughly
5. **Lint** your code: `composer lint` for PHP, `npm run lint` for JS
6. **Commit** your changes with descriptive messages: `git commit -m 'Add amazing feature'`
7. **Push** to your branch: `git push origin feature/amazing-feature`
8. **Open** a Pull Request

### Code Standards
- **PHP**: Follow PSR-12 coding standards
- **JavaScript**: Use Biome.js for formatting and linting
- **Commit Messages**: Use descriptive, imperative tense
- **Documentation**: Update both code comments and README when needed

### Pull Request Guidelines
- Keep changes focused and single-purpose
- Ensure your code follows existing patterns
- Update documentation when adding new features

## Security

This project follows security best practices:

- **Input Validation**: All user inputs are validated and sanitized
- **SQL Injection Prevention**: Use prepared statements or ORM when needed
- **XSS Prevention**: Proper output escaping in HTML views
- **CSRF Protection**: Cross-site request forgery protection enabled
- **File Upload Security**: Restricted file types and size limits
- **Rate Limiting**: API endpoint protection against abuse

### Security Checklist
- Review and update dependencies regularly
- Implement proper error handling without leaking sensitive information
- Use HTTPS in production
- Configure security headers
- Implement proper authentication and authorization

## Troubleshooting

### Common Issues

**Asset Loading Problems**
- Ensure Vite development server is running
- Check asset paths in `vite.config.js`
- Clear browser cache and try hard refresh (Ctrl+F5)

**Permission Issues**
- Ensure web server has write access to `public/uploads/`
- Check file permissions aren't too restrictive (755 for directories, 644 for files)

**Development Server Won't Start**
- Check if ports are already in use
- Verify Node.js and PHP are installed correctly
- Check for syntax errors in configuration files

### Performance Optimization
- Use production builds (`npm run build`) before deployment
- Enable opcache in PHP for better performance
- Use a CDN for static assets in production
- Implement database caching where appropriate

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

The MIT License is simple and permissive, allowing you to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

## Acknowledgments

### Core Technologies
- [FlightPHP](https://flightphp.com/) - Lightweight PHP microframework
- [Vite](https://vitejs.dev/) - Next-generation frontend tooling
- [Tailwind CSS](https://tailwindcss.com/) - Utility-first CSS framework
- [Alpine.js](https://alpinejs.dev/) - Declarative JavaScript framework

### Development Tools
- [Biome.js](https://biomejs.dev/) - All-in-one JavaScript formatter and linter
- [Composer](https://getcomposer.org/) - PHP dependency manager

---

## Need Help?

- Check the [troubleshooting section](#troubleshooting) for common issues
- Review the [contributing guidelines](#contributing) for development process
- Feel free to open an [issue](https://github.com/your-repo/issues) for bugs or feature requests

---
