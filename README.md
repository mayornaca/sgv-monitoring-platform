# SGV Monitoring Platform

## 🏗️ Project Overview

Comprehensive monitoring platform for industrial devices, vehicle fleet management, and infrastructure monitoring. Built with Symfony 6.4 and designed for multi-tenant SaaS deployment.

## 🚀 Features

### Current Implementation
- **Device Monitoring**: Multi-protocol device status tracking (ICMP, OPC, SCADA)
- **Alert System**: Multi-channel notifications (WhatsApp, Email, SMS, Browser)
- **Real-time Dashboard**: Bootstrap 5 responsive interface with Isotope masonry layout
- **Multi-tenant Support**: EasyAdmin integration with tenant isolation
- **Reporting**: Automated PDF report generation

### Supported Protocols
- **Method 1**: Standard ICMP/HTTP monitoring
- **Method 3**: OPC/SCADA industrial protocols
- **Method 4**: Traffic sensors (Espiras)
- **Method 6**: Environmental monitoring
- **Method 7**: Emergency systems (SOS, barriers, doors)

## 🛠️ Technology Stack

- **Backend**: Symfony 6.4 LTS
- **Database**: PostgreSQL with Doctrine ORM
- **Frontend**: Bootstrap 5, jQuery, Isotope.js
- **Authentication**: JWT + 2FA (TOTP/Email)
- **Admin Interface**: EasyAdmin Bundle 4.x
- **Notifications**: Symfony Notifier + Custom WhatsApp integration

## 📁 Project Structure

```
src/
├── Controller/          # Symfony controllers
├── Entity/             # Doctrine entities
├── Service/            # Business logic services
├── Repository/         # Data access layer
├── Command/            # Console commands
├── Notification/       # Alert notification system
└── Twig/              # Custom Twig extensions

templates/
├── dashboard/cot/      # COT monitoring interface
├── admin/             # Admin interface templates
└── bundles/           # EasyAdmin overrides
```

## 🔧 Development Setup

### Requirements
- PHP 8.1+
- PostgreSQL 13+
- Composer
- Node.js (for asset compilation)

### Installation
```bash
# Clone repository
git clone https://github.com/mayornaca/sgv-monitoring-platform.git
cd sgv-monitoring-platform

# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit database configuration in .env

# Setup database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Install assets
php bin/console assets:install
```

## 🎯 Deployment Targets

- **Development**: vs.gvops.cl
- **Production**: sgv.costaneranorte.cl

## 📊 Business Model

Designed for transformation from custom implementation to multi-tenant SaaS platform:

- **Freemium**: 10 devices, basic monitoring
- **Professional**: 200 devices, advanced analytics
- **Enterprise**: 1000+ devices, custom integrations
- **Industrial**: Unlimited devices, on-premise options

## 🤖 Development Workflow

This project implements workflow optimization agents based on [contains-studio/agents](https://github.com/contains-studio/agents):

- **backend-architect**: API design and system architecture
- **test-writer-fixer**: Automated testing and quality assurance
- **project-shipper**: Deployment automation and release management

## 📈 Roadmap

### Phase 1: Contract Completion (Current)
- Complete COT monitoring system
- Implement remaining alert channels
- Finalize reporting system

### Phase 2: SaaS Transformation
- Multi-tenant architecture refinement
- Cloud-native deployment (Oracle Cloud)
- API-first architecture
- Freemium tier implementation

### Phase 3: Market Expansion
- White-label solutions
- Custom protocol integrations
- Advanced analytics with Grafana
- Mobile applications

## 🔐 Security

- JWT authentication with refresh tokens
- Two-factor authentication (TOTP/Email)
- Role-based access control (RBAC)
- Tenant data isolation
- Audit logging for all operations

## 📞 Support

For development questions and support:
- Email: development@gesvial.cl
- Documentation: [Internal Wiki]

---

**Note**: This project contains proprietary business logic for industrial monitoring systems. Handle with appropriate confidentiality.