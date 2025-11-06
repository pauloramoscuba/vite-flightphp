# Vite-FlightPHP

A modern PHP web application skeleton that combines the FlightPHP microframework with Vite for frontend asset management. This boilerplate provides a solid foundation for building web applications with PHP on the backend and modern frontend development tools.

## Features

- **FlightPHP 3.x** - Lightweight PHP microframework
- **Vite 7.x** - Next-generation frontend tooling for fast development
- **Tailwind CSS 4.x** - Utility-first CSS framework
- **Alpine.js 3.x** - Lightweight JavaScript framework
- **Hot Module Replacement (HMR)** - Instant updates during development
- **Dark Mode Support** - Built-in dark/light theme toggle
- **PSR-12 Compliant** - PHP coding standards
- **Biome.js** - JavaScript formatting and linting
- **Responsive Design** - Mobile-first approach

## Prerequisites

- PHP 8.0 or higher
- Composer
- Node.js (18.x or higher)

## Installation

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

## Project Structure

```
vite-flightphp/
├── app/                    # PHP application code
│   ├── config/            # Configuration files
│   ├── controllers/       # Request controllers
│   ├── middlewares/       # Middleware implementations
│   └── views/             # PHP view templates
├── public/                # Web root directory
│   ├── index.php          # Frontend controller
│   └── dist/              # Production Vite assets
├── vite/                  # Frontend build configuration
│   ├── src/               # Frontend source files
│   ├── package.json       # Frontend dependencies
│   └── vite.config.js     # Vite build configuration
├── composer.json          # PHP dependencies
└── ...
```

## Development Commands

### PHP Commands
- Start PHP development server: `composer start`
- Install PHP dependencies: `composer install`

### Frontend Commands
From the `vite/` directory:

- Start development server: `npm run dev`
- Build for production: `npm run build`

## Frontend Integration

This project uses a custom integration between Vite and PHP to handle assets. During development:

- Vite serves assets from `localhost:5173`
- PHP application proxies requests to the Vite server
- Assets are automatically injected into PHP views using the `vite()->entry()` helper

In production:

- Built assets are served from `public/dist/`
- Asset manifest is used for cache-busting

## API Endpoints

The skeleton includes example API endpoints:

- `GET /api/users` - Get all users
- `GET /api/users/{id}` - Get user by ID
- `POST /api/users/{id}` - Update user

## Configuration

Configuration is managed through:

- `app/config/config.php` - Main application configuration
- `app/config/routes.php` - Route definitions
- `app/config/services.php` - Service definitions
- `vite/vite.config.js` - Vite build configuration

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

- [FlightPHP](https://flightphp.com/) - PHP microframework
- [Vite](https://vitejs.dev/) - Next-generation frontend tooling
- [Tailwind CSS](https://tailwindcss.com/) - CSS framework