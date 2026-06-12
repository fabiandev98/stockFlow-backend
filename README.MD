# Deneb Backend

Deneb Backend utiliza Laravel como framework base. Para más detalles sobre Laravel, consulta la [documentación oficial](https://laravel.com/docs).

## Requisitos

-   PHP 8.3 o superior
-   [Composer](https://getcomposer.org/)
-   Una base de datos compatible (MySQL, PostgreSQL, etc.)

> **Nota:** Ya no existe un entorno Docker para desarrollo. La ejecución local se realiza directamente en tu máquina.

## Características

-   API optimizada exclusivamente para uso como backend (no se gestionan vistas por rutas web, aunque siguen disponibles si se requieren).
-   Autenticación basada en Laravel Sanctum usando access tokens.
-   Gestión de sesión y tokens en base de datos.
-   Gestión de roles y permisos mediante Laravel Permissions de Spatie:
    -   Sistema RBAC completo basado en Policies de Laravel.
    -   Estandarización de permisos a través del enumerable `DenebPermission`.
-   Módulo de API Keys revocables.

## Configuración del Archivo `.env`

Este proyecto utiliza un único archivo `.env` para definir las variables de entorno, incluyendo la configuración de la base de datos. Asegúrate de copiar el archivo `.env.example` a `.env` y personalizarlo según tu entorno local.

## Instalación y Ejecución Local

1. Instala las dependencias de PHP y Composer en tu sistema:

    - [Guía de instalación de PHP 8.3](https://www.php.net/manual/es/install.php)
    - [Guía de instalación de Composer](https://getcomposer.org/download/)

2. Instala las dependencias del proyecto:

    ```sh
    composer install
    ```

3. Copia el archivo de entorno y configura tus variables:

    ```sh
    cp .env.example .env
    # Edita .env según tu configuración local
    ```

4. Genera la clave de la aplicación:

    ```sh
    php artisan key:generate
    ```

5. Ejecuta las migraciones y, si lo deseas, los seeders:

    ```sh
    php artisan migrate
    ```

6. Ejecuta el comando de initialización y sigue sus pasos:

    ```sh
    php artisan deneb:init
    ```

7. Inicia el servidor de desarrollo:
    ```sh
    php artisan serve
    ```

Accede a la aplicación en [http://localhost:8000](http://localhost:8000).

### Notas:

-   Los cambios en el código se reflejan en tiempo real.
-   La base de datos debe estar configurada correctamente en el archivo `.env`.

## Entorno de Producción

**Archivos:** `docker-compose.prod.yml` y `Dockerfile`

En producción, el código se incluye en la imagen Docker para mayor seguridad, y solo el directorio `storage/` se monta como volumen para persistir datos.

### Cómo Ejecutar

Asegúrate de que el archivo `.env` esté configurado con la conexión a la base de datos.

Construye y ejecuta la aplicación con:

```sh
docker compose -f docker-compose.prod.yml up --build
```

Si no se está usando Docker, asegúrese que el servidor esté apuntando al archivo `index.php` en `/public` como punto de partida y que los demás directorios estén protegidos.

---

# Deneb Backend

Deneb Backend uses Laravel as its base framework. For more details on Laravel, check the [official documentation](https://laravel.com/docs).

## Requirements

-   PHP 8.3 or higher
-   [Composer](https://getcomposer.org/)
-   A compatible database (MySQL, PostgreSQL, etc.)

> **Note:** There is no longer a Docker environment for development. Local execution is done directly on your machine.

## Features

-   API optimized exclusively for backend use (no web views are handled by routes, although they are still available if needed).
-   Authentication using Laravel Sanctum with access tokens.
-   Session and token management in the database.
-   Role and permission management using Laravel Permissions (Spatie):
    -   Full RBAC system based on Laravel Policies.
    -   Standardized permissions through the `DenebPermission` enum.
-   Revocable API Keys module.

## `.env` File Configuration

This project uses a single `.env` file to define environment variables, including database configuration. Make sure to copy the `.env.example` file to `.env` and customize it for your local environment.

## Installation and Local Execution

1. Install PHP and Composer dependencies on your system:

    - [PHP 8.3 installation guide](https://www.php.net/manual/en/install.php)
    - [Composer installation guide](https://getcomposer.org/download/)

2. Install project dependencies:

    ```sh
    composer install
    ```

3. Copy the environment file and configure your variables:

    ```sh
    cp .env.example .env
    # Edit .env as needed for your local setup
    ```

4. Generate the application key:

    ```sh
    php artisan key:generate
    ```

5. Run migrations and, if desired, seeders:

    ```sh
    php artisan migrate
    ```

6. Run the initialization command and follow its steps:

    ```sh
    php artisan deneb:init
    ```

7. Start the development server:
    ```sh
    php artisan serve
    ```

Access the application at [http://localhost:8000](http://localhost:8000).

### Notes:

-   Code changes are reflected in real time.
-   The database must be properly configured in the `.env` file.

## Production Environment

**Files:** `docker-compose.prod.yml` and `Dockerfile`

In production, the code is included in the Docker image for better security, and only the `storage/` directory is mounted as a volume to persist data.

### How to Run

Make sure the `.env` file is configured with the database connection.

Build and run the application with:

```sh
docker compose -f docker-compose.prod.yml up --build
```

If not using Docker, make sure your server points to the `index.php` file inside `/public` as the entry point and that other directories are protected.
