# Video Sharing Platform - API (Backend)

*Leia em [Inglês](README.md).*

![Laravel](https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Firebase](https://img.shields.io/badge/Firebase-Auth-FFCA28?style=for-the-badge&logo=firebase&logoColor=black)

Uma API RESTful robusta construída em **Laravel 12** que alimenta uma rede social de compartilhamento multimídia. Este repositório contém apenas a aplicação Backend.

🔗 **[Clique aqui para ver o repositório do Front-end em Vue 3](https://github.com/Luiz-Henrique28/video-frontend)**.

## Sobre a Arquitetura

Este projeto foi desenhado para lidar com fluxos de mídia pesados de forma assíncrona, garantindo uma experiência de usuário fluida. Inspirado na dinâmica das principais redes sociais, o sistema oferece rotas públicas para que visitantes possam explorar o feed e perfis sem autenticação, exigindo login apenas para interações e criação de conteúdo. Ainda em desenvolvimento, a ideia final é construir uma plataforma robusta de compartilhamento de mídia, onde as pessoas possam interagir e compartilhar seus conteúdos de forma segura, rápida e fácil.

## Principais Destaques Técnicos

* **Processamento Assíncrono de Mídia:** O upload suporta múltiplos arquivos por postagem. Quando um vídeo é enviado, a API dispara um *Queue Job* em background (`GenerateThumbFromVideo`) que utiliza **FFmpeg** para extrair uma thumbnaildo vídeo, evitando o bloqueio da requisição HTTP principal.
* **Motor de Busca Unificado:** Um endpoint `/search` otimizado que usa uma instrução SQL `UNION` bruta para buscar tanto Usuários (por prefixo no nome) quanto Tags (por substring) simultaneamente.
* **Controle de Acesso Inteligente:** Middleware customizado `EnsureProfileIsComplete` que bloqueia novos usuários de criar conteúdo ou comentar até que finalizem a configuração básica do perfil.
* **Integridade de Dados:** Validação rigorosa de autoria nos *Controllers* de exclusão de mídia e posts, garantindo que os usuários só consigam modificar e deletar seus próprios recursos.


## Banco de Dados e Relacionamentos

O banco de dados relacional foi modelado em MySQL 8 e é gerenciado através das melhores práticas do ecossistema Laravel. O projeto utiliza `Migrations` para versionamento seguro do schema, `Factories e Seeders` para popular o ambiente rapidamente, e uma modelagem relacional robusta, garantindo a integridade dos dados e alta produtividade na evolução do código.

## Tecnologias e Ferramentas

* **Framework:** Laravel 12.0 (PHP 8.2+)
* **Banco de Dados:** MySQL 8
* **Autenticação:** Laravel Sanctum + Firebase (`kreait/firebase-php`)
* **Processamento de Mídia:** FFmpeg (`php-ffmpeg/php-ffmpeg`)
* **Infraestrutura:** Ambiente de desenvolvimento totalmente conteinerizado utilizando Docker e Docker Compose.


## Como Executar Localmente

### 1. Pré-requisitos
Você precisa ter o **Docker** e o **Docker Compose** instalados na sua máquina.

### 2. Configuração do Ambiente
Clone o repositório e configure as variáveis de ambiente. Certifique-se de ter as credenciais (*Service Account*) do seu projeto Firebase em mãos.

```bash
git clone [https://github.com/Luiz-Henrique28/video-api.git](https://github.com/Luiz-Henrique28/video-api.git)
cd video-api
cp .env.example .env
```
*Não se esqueça de preencher as variáveis `DB_*` e `FIREBASE_*` no seu arquivo `.env` recém-criado.*

### 3. Subindo os Containers
Este projeto utiliza um arquivo compose de desenvolvimento específico. Rode os seguintes comandos para "buildar" os containers, instalar as dependências e executar as migrations:

```bash
# Inicie os containers do Docker (App + Banco MySQL)
docker-compose -f docker-compose.dev.yml up -d

# Instale as dependências do PHP e do Node
docker-compose exec app composer install
docker-compose exec app npm install

docker-compose exec app php artisan key:generate

docker-compose exec app php artisan migrate --seed
```

A API estará rodando e disponível em `http://localhost:8000`.

### 4. Rodando o Queue Worker (Muito Importante)
Para testar a geração assíncrona das thumbnails de vídeo, você **deve** iniciar o *worker* de filas do Laravel dentro do container. Sem isso, os vídeos farão upload, mas as capas não serão geradas:

```bash
docker-compose exec app php artisan queue:work
```


## Visão Geral dos Endpoints

Aqui está um resumo das principais rotas expostas pela API:

**Rotas Públicas:**
* `POST /api/auth/firebase` - Valida e troca o Token do Firebase por um Token do Sanctum.
* `GET /api/post` - Lista o feed de postagens paginado (16 itens por página).
* `GET /api/users/{user:name}` - Retorna o perfil público de um usuário.
* `GET /api/search?q={termo}` - Busca unificada (Usuários e Tags).

**Rotas Protegidas (Requer Token Sanctum & Perfil Completo):**
* `POST /api/post` - Cria uma nova postagem.
* `POST /api/media` - Realiza o upload de imagem/vídeo (Dispara o Job do FFmpeg).
* `POST /api/comment` - Adiciona um comentário a um post.
* *(Inclui operações completas de CRUD para Posts, Mídias e Comentários do usuário logado)*