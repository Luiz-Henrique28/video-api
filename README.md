# Video Sharing Platform - API (Backend)

*Read this in [Portuguese](README.pt-br.md).*

![Laravel](https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Firebase](https://img.shields.io/badge/Firebase-Auth-FFCA28?style=for-the-badge&logo=firebase&logoColor=black)

A robust RESTful API built with **Laravel 12** that powers a multimedia sharing social network. This repository contains only the Backend application.

🔗 **[Click here to see the Vue 3 Front-end repository](https://github.com/Luiz-Henrique28/video-frontend)**.

## About the Architecture

This project was designed to handle heavy media workflows asynchronously, ensuring a smooth user experience. Inspired by the dynamics of major social networks, the system provides public routes for visitors to explore the feed and profiles without authentication, requiring login only for interactions and content creation. Still in development, the ultimate idea is to build a robust media sharing platform where people can interact and share their content safely, quickly, and easily.

## Key Technical Features

* **Asynchronous Media Processing:** Uploads support multiple files per post. When a video is uploaded, the API dispatches a background *Queue Job* (`GenerateThumbFromVideo`) that uses **FFmpeg** to extract a video thumbnail, preventing the main HTTP request from blocking.
* **Unified Search Engine:** An optimized `/search` endpoint using a raw SQL `UNION` statement to search both Users (by prefix) and Tags (by substring) simultaneously.
* **Smart Access Control:** A custom `EnsureProfileIsComplete` middleware that blocks new users from creating content or commenting until they finish their basic profile setup.
* **Data Integrity:** Strict author validation in media and post deletion *Controllers*, ensuring users can only modify and delete their own resources.

## Database & Relationships

The relational database is modeled in MySQL 8 and managed using the Laravel ecosystem's best practices. The project utilizes `Migrations` for secure schema versioning, `Factories and Seeders` to quickly populate the environment, and robust relational modeling, ensuring data integrity and high productivity when evolving the codebase.

## Tech Stack & Tools

* **Framework:** Laravel 12.0 (PHP 8.2+)
* **Database:** MySQL 8
* **Authentication:** Laravel Sanctum + Firebase (`kreait/firebase-php`)
* **Media Processing:** FFmpeg (`php-ffmpeg/php-ffmpeg`)
* **Infrastructure:** Fully containerized development environment using Docker and Docker Compose.


## How to Run Locally

### 1. Prerequisites
You need to have **Docker** and **Docker Compose** installed on your machine.

### 2. Environment Setup
Clone the repository and set up your environment variables. Ensure you have your Firebase Service Account credentials available.

```bash
git clone [https://github.com/Luiz-Henrique28/video-api.git](https://github.com/Luiz-Henrique28/video-api.git)
cd video-api
cp .env.example .env
```
*Do not forget to fill in the `DB_*` and `FIREBASE_*` variables in your newly created `.env` file.*

### 3. Spin up the Containers
This project uses a specific development compose file. Run the following commands to build the containers, install dependencies, and run migrations:

```bash
# Start the Docker containers (App + MySQL DB)
docker-compose -f docker-compose.dev.yml up -d

# Install PHP and Node dependencies
docker-compose exec app composer install
docker-compose exec app npm install

docker-compose exec app php artisan key:generate

docker-compose exec app php artisan migrate --seed
```

The API will be up and running at `http://localhost:8000`.

### 4. Running the Queue Worker (Crucial)
To test the asynchronous video thumbnail generation, you **must** start the Laravel queue worker inside the container. Without this, videos will upload, but the thumbnails will not be generated:

```bash
docker-compose exec app php artisan queue:work
```


## API Endpoints Overview

Here is a summary of the main routes exposed by the API:

**Public Routes:**
* `POST /api/auth/firebase` - Validates and exchanges the Firebase Token for a Sanctum Token.
* `GET /api/post` - Lists the paginated post feed (16 items per page).
* `GET /api/users/{user:name}` - Returns a user's public profile.
* `GET /api/search?q={term}` - Unified search (Users and Tags).

**Protected Routes (Requires Sanctum Token & Complete Profile):**
* `POST /api/post` - Creates a new post.
* `POST /api/media` - Uploads an image/video (Triggers the FFmpeg Job).
* `POST /api/comment` - Adds a comment to a post.
* *(Includes full CRUD operations for Posts, Media, and Comments for the logged-in user)*