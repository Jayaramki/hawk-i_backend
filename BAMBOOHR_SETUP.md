# BambooHR Integration Setup Guide

This guide will help you set up the BambooHR integration for the Hawk-i application.

## Prerequisites

- Laravel 10+ application
- MySQL/MariaDB database
- BambooHR account with API access
- PHP 8.1+ with required extensions

## 1. Environment Configuration

Copy the `.env.example` file to `.env` and configure the following BambooHR variables:

```bash
# BambooHR Configuration
BAMBOOHR_API_KEY=your-bamboohr-api-key
BAMBOOHR_SUBDOMAIN=yourcompany
BAMBOOHR_BASE_URL=https://api.bamboohr.com/api/gateway.php
```

### Getting BambooHR API Key

1. Log in to your BambooHR account
2. Go to **Settings** → **API**
3. Generate a new API key
4. Copy the API key to your `.env` file

### BambooHR Subdomain

Your subdomain is the part before `.bamboohr.com` in your BambooHR URL:
- If your URL is `https://acme.bamboohr.com`, your subdomain is `acme`
- If your URL is `https://mycompany.bamboohr.com`, your subdomain is `mycompany`

## 2. Database Setup

Run the database migrations to create the required tables:

```bash
php artisan migrate
```

This will create the following tables:
- `bamboohr_departments` - Department information
- `bamboohr_job_titles` - Job title information  
- `bamboohr_employees` - Employee information
- `bamboohr_time_off` - Time off requests

## 3. API Routes

The following API endpoints are available:

### Status & Connection
- `GET /api/v1/bamboohr/status` - Get sync status and statistics
- `GET /api/v1/bamboohr/test-connection` - Test BambooHR connection

### Sync Operations
- `POST /api/v1/bamboohr/sync-all` - Sync all data
- `POST /api/v1/bamboohr/sync-employees` - Sync employees only
- `POST /api/v1/bamboohr/sync-departments` - Sync departments only
- `POST /api/v1/bamboohr/sync-job-titles` - Sync job titles only
- `POST /api/v1/bamboohr/sync-time-off` - Sync time off requests only
- `POST /api/v1/bamboohr/clear-cache` - Clear cache and reset sync status

### Data Retrieval
- `GET /api/v1/bamboohr/employees` - Get employees with pagination and filters
- `GET /api/v1/bamboohr/departments` - Get departments with pagination and filters
- `GET /api/v1/bamboohr/job-titles` - Get job titles with pagination and filters
- `GET /api/v1/bamboohr/time-off` - Get time off requests with pagination and filters
- `GET /api/v1/bamboohr/sync-history` - Get sync history

### Individual Records
- `GET /api/v1/bamboohr/employees/{id}` - Get specific employee
- `GET /api/v1/bamboohr/departments/{id}` - Get specific department
- `GET /api/v1/bamboohr/time-off/{id}` - Get specific time off request

## 4. Testing the Integration

### Test Connection

First, test your connection to BambooHR:

```bash
curl -X GET "http://localhost:8000/api/v1/bamboohr/test-connection"
```

### Get Status

Check the current sync status:

```bash
curl -X GET "http://localhost:8000/api/v1/bamboohr/status"
```

### Sync Data

Start with a small sync to test:

```bash
curl -X POST "http://localhost:8000/api/v1/bamboohr/sync-departments"
```

## 5. Frontend Integration

The frontend component is already set up to use these API endpoints. Make sure your Angular environment is configured with the correct API URL:

```typescript
// src/environments/environment.ts
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000'
};
```

## 6. Troubleshooting

### Common Issues

1. **API Key Invalid**
   - Verify your BambooHR API key is correct
   - Ensure the API key has the necessary permissions

2. **Subdomain Incorrect**
   - Double-check your BambooHR subdomain
   - The subdomain should match exactly what's in your BambooHR URL

3. **Connection Timeout**
   - Check your internet connection
   - Verify BambooHR's API status
   - Check firewall settings

4. **Database Errors**
   - Ensure all migrations have been run
   - Check database connection settings
   - Verify table permissions

### Logs

Check Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

### API Response Format

All API responses follow this format:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

## 7. Security Considerations

- Keep your BambooHR API key secure
- Use HTTPS in production
- Implement rate limiting if needed
- Consider implementing API authentication for production use

## 8. Performance Tips

- Sync data during off-peak hours
- Use the individual sync endpoints for targeted updates
- Monitor sync performance and adjust timeouts as needed
- Consider implementing background jobs for large sync operations

## 9. Support

If you encounter issues:

1. Check the Laravel logs first
2. Verify your BambooHR API credentials
3. Test the connection endpoint
4. Check the database migrations
5. Review the API response format

## 10. Next Steps

After successful setup:

1. Test all sync operations
2. Monitor sync performance
3. Set up regular sync schedules if needed
4. Customize the frontend component as needed
5. Implement additional error handling if required
