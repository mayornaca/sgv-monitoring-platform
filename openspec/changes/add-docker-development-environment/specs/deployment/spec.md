# Deployment Capability

## ADDED Requirements

### Requirement: Docker Container Support
The system SHALL provide Docker containers for local development environment that replicate the production stack.

#### Scenario: Developer clones and runs project locally
- **GIVEN** a developer has Docker Desktop installed
- **WHEN** they clone the repository and run `docker-compose up`
- **THEN** all services (PHP-FPM, Nginx, MySQL, PostgreSQL) start successfully
- **AND** the application is accessible at http://localhost
- **AND** databases are initialized with proper schemas

#### Scenario: Code changes reflect immediately
- **GIVEN** Docker containers are running
- **WHEN** a developer modifies PHP code in their local IDE
- **THEN** the changes are immediately visible in the browser without rebuilding containers
- **AND** no container restart is required

#### Scenario: Database persistence across restarts
- **GIVEN** Docker containers are running with data in databases
- **WHEN** the developer stops containers with `docker-compose down`
- **AND** restarts with `docker-compose up`
- **THEN** all database data persists (MySQL and PostgreSQL)
- **AND** no data is lost

### Requirement: Multi-Database Support
The Docker environment SHALL support both MySQL and PostgreSQL databases matching production configuration.

#### Scenario: MySQL database accessible
- **GIVEN** Docker containers are running
- **WHEN** the application connects to MySQL service
- **THEN** it connects successfully to `gesvial_sgv` database
- **AND** can execute queries on `default` entity manager

#### Scenario: PostgreSQL database accessible
- **GIVEN** Docker containers are running
- **WHEN** the application connects to PostgreSQL service
- **THEN** it connects successfully to `dbpuente` database
- **AND** can execute queries on `siv` entity manager
- **AND** stored functions are available

### Requirement: PHP Extension Compatibility
The Docker PHP container SHALL include all required extensions for the Symfony application.

#### Scenario: PDF generation works in Docker
- **GIVEN** Docker containers are running
- **WHEN** the application generates a PDF report using Knp Snappy
- **THEN** wkhtmltopdf executable is available
- **AND** PDF is generated successfully

#### Scenario: Excel generation works in Docker
- **GIVEN** Docker containers are running
- **WHEN** the application generates an Excel file using PhpSpreadsheet
- **THEN** the file is created successfully with correct formatting
- **AND** all 16 columns are populated

#### Scenario: Database drivers available
- **GIVEN** Docker PHP container is built
- **WHEN** PHP checks for extensions
- **THEN** pdo_mysql extension is loaded
- **AND** pdo_pgsql extension is loaded
- **AND** all required extensions are available

### Requirement: Development Environment Isolation
The Docker environment SHALL be isolated from the host system and not interfere with production deployment.

#### Scenario: Docker environment uses separate configuration
- **GIVEN** `.env.docker` file exists
- **WHEN** containers start
- **THEN** environment variables are loaded from `.env.docker`
- **AND** production `.env` file is not modified
- **AND** production `.env.prod` file is not modified

#### Scenario: Production deployment unaffected
- **GIVEN** Docker files exist in repository
- **WHEN** code is deployed to production server
- **THEN** production deployment process remains unchanged
- **AND** Docker files are ignored in production
- **AND** no breaking changes to existing workflows

### Requirement: Documentation and Onboarding
The Docker setup SHALL be documented with clear instructions for new developers.

#### Scenario: New developer can start development
- **GIVEN** a new developer has repository access
- **WHEN** they follow `docs/DOCKER_SETUP.md` instructions
- **THEN** they can run the application locally within 15 minutes
- **AND** all prerequisites are clearly documented
- **AND** troubleshooting steps are provided

#### Scenario: Common tasks are documented
- **GIVEN** Docker environment is running
- **WHEN** developer needs to run migrations
- **THEN** documentation provides exact command to execute
- **AND** documentation shows how to access logs
- **AND** documentation shows how to access database shells
