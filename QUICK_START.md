# 🚀 BambooHR Integration - Quick Start Guide

## ⚡ **Get Running in 5 Minutes!**

### **Step 1: Set Up Environment Variables**

1. **Copy the environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Add your BambooHR credentials to `.env`:**
   ```bash
   # BambooHR Configuration
   BAMBOOHR_API_KEY=your-actual-bamboohr-api-key
   BAMBOOHR_SUBDOMAIN=yourcompany
   BAMBOOHR_BASE_URL=https://api.bamboohr.com/api/gateway.php
   ```

### **Step 2: Run Database Migrations**

```bash
php artisan migrate
```

### **Step 3: Test the Integration**

```bash
# Test the service
php test_bamboohr.php

# Or test via API
curl -X GET "http://localhost:8000/api/v1/bamboohr/test-connection"
```

### **Step 4: Access the Frontend**

1. **Start your Angular dev server:**
   ```bash
   cd ../hawk-i_frontend
   ng serve
   ```

2. **Navigate to the integration:**
   ```
   http://localhost:4200/integrations/bamboohr
   ```

## 🎯 **What You'll See**

### **Backend API Endpoints**
- ✅ `GET /api/v1/bamboohr/status` - Get sync status
- ✅ `GET /api/v1/bamboohr/test-connection` - Test connection
- ✅ `POST /api/v1/bamboohr/sync-all` - Sync all data
- ✅ `POST /api/v1/bamboohr/sync-employees` - Sync employees
- ✅ `POST /api/v1/bamboohr/sync-time-off` - Sync time off requests

### **Frontend Features**
- 🎨 **Beautiful UI** with real-time updates
- 📊 **Live statistics** dashboard
- 🔄 **Sync controls** for all data types
- ⚡ **Connection testing** with status indicators
- 📱 **Responsive design** for mobile and desktop

## 🛠️ **Getting Your BambooHR API Key**

1. **Log in to BambooHR**
2. **Go to Settings → API**
3. **Generate a new API key**
4. **Copy it to your `.env` file**

## 🔧 **Troubleshooting**

### **Error: "API key not configured"**
- Make sure you've added `BAMBOOHR_API_KEY` to your `.env` file
- Restart your Laravel server after changing `.env`

### **Error: "Connection failed"**
- Verify your API key is correct
- Check your BambooHR subdomain
- Ensure your internet connection is working

### **Frontend not loading**
- Make sure Angular dev server is running
- Check that `environment.ts` has the correct API URL
- Verify CORS is configured in Laravel

## 🎉 **You're Ready!**

Once you've completed these steps, you'll have:

- ✅ **Full backend API** for BambooHR integration
- ✅ **Real-time frontend** with live data updates
- ✅ **Complete sync functionality** for all HR data
- ✅ **Professional UI** with error handling

## 📚 **Next Steps**

- **Test the connection** using the frontend interface
- **Sync your data** to see employees, departments, and time off requests
- **Explore the API** endpoints for custom integrations
- **Customize the UI** to match your brand

## 🆘 **Need Help?**

- Check the **`BAMBOOHR_SETUP.md`** for detailed setup instructions
- Review the **`BAMBOOHR_INTEGRATION_SUMMARY.md`** for implementation details
- Check Laravel logs: `tail -f storage/logs/laravel.log`

---

**🎯 The integration is now ready for production use!**
