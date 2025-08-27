# Phare Framework - Missing Features TODO

This document lists the missing features that need to be implemented to make Phare a comprehensive PHP framework.

## Database & ORM

- [x] Implement database migration system
- [x] Implement database seeding functionality  
- [x] Create schema builder for table creation/modification
- [ ] Add multiple database connection support
- [x] Implement database factory for test data generation

## Validation

- [x] Create comprehensive Validator class
- [x] Implement Form Request Validation
- [x] Add custom validation rules system
- [x] Implement validation error handling

## Events & Monitoring

- [ ] Create Event system with Observer pattern
- [ ] Implement Listeners & Subscribers
- [ ] Add application events (booting, booted, etc)

## File & Storage

- [ ] Create file system abstraction
- [ ] Add cloud storage integration (S3, GCS)
- [ ] Implement file upload handling

## HTTP Features

- [ ] Add rate limiting functionality
- [ ] Create API resources for JSON transformation
- [ ] Implement pagination system
- [ ] Add file downloads/streaming support

## Security

- [ ] Implement CSRF protection
- [ ] Add XSS protection
- [ ] Create encryption/hashing utilities
- [ ] Implement API authentication (Sanctum equivalent)

## Template & Views

- [ ] Add view composers functionality
- [ ] Implement view sharing
- [ ] Extend template inheritance system

## Configuration & Environment

- [ ] Complete configuration caching implementation
- [ ] Add environment-specific config support

## CLI & Commands

- [ ] Create make commands (controller, model, middleware)
- [x] Add database commands (migrate, seed)
- [ ] Create clear commands (cache, config)

## Additional Features

- [ ] Implement localization/translation system
- [ ] Create mail system
- [ ] Implement notification system
- [ ] Complete job queues implementation
- [ ] Add task scheduling system
- [ ] Implement broadcasting (WebSocket support)

---

**Priority**: Start with Database & ORM features, then Validation, followed by Security features.