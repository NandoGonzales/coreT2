# HR4 Employee Directory Integration - Implementation Summary

## Overview
Successfully integrated the HR4 Employee Directory into the CoreT2 User Management system. This integration allows Super Admins to sync employee data from the external HR4 system and optionally link HR4 employees to CoreT2 user accounts.

## Files Created

### 1. Database Setup
**File:** `admin/User-Management-Role-Based-Access/setup_hr4_tables.php`
- Creates `hr4_employees` table to store synced employee data
- Creates `user_hr4_link` table to manage 1:1 mappings between CoreT2 users and HR4 employees
- Stores full employee details including department, job title, location, status, and raw JSON

### 2. HR4 API Client
**File:** `classes/HR4EmployeeClient.php`
- Handles communication with the HR4 API endpoint
- Fetches employee data from `https://hr4.microfinancial-1.com/allemployees`
- Includes error handling for connection failures and invalid JSON responses
- Uses API key authentication (configurable)

### 3. Sync Endpoint
**File:** `admin/User-Management-Role-Based-Access/sync_hr4_employees.php`
- Super Admin only access
- Fetches all employees from HR4 API
- Performs upsert operations (insert or update) based on `hr4_employee_id`
- Maps HR4 fields to database schema
- Logs sync actions to audit trail
- Returns success/error status with sync count

### 4. Link/Unlink Actions
**File:** `admin/User-Management-Role-Based-Access/hr4_link_action.php`
- Super Admin only access
- **Link Action:** Creates association between CoreT2 user and HR4 employee
  - Validates both user and employee exist
  - Enforces 1:1 mapping (prevents duplicate links)
  - Logs link action to audit trail
- **Unlink Action:** Removes association
  - Can unlink by user_id or hr4_employee_id
  - Logs unlink action to audit trail

### 5. Employee List Endpoint
**File:** `admin/User-Management-Role-Based-Access/hr4_employee_list.php`
- Super Admin only access
- Returns all HR4 employees with their link status
- Joins with `user_hr4_link` and `users` tables to show linked username
- Used by the frontend to display the employee directory

### 6. Frontend Integration
**File:** `admin/User-Management-Role-Based-Access/hr4_integration.js`
- Loads and displays HR4 employee directory
- Implements search/filter functionality
- Handles sync button with loading state
- Manages link/unlink operations with confirmation dialogs
- Pre-fills user creation form from employee data
- Shows link status badges (Linked/Unlinked)

### 7. UI Updates
**File:** `admin/User-Management-Role-Based-Access/user_management.php`
- Added "Sync with HR4" button in page header
- Added HR4 Employee Directory table below User Directory
- Added Link Employee modal for selecting CoreT2 user to link
- Integrated hr4_integration.js script
- Displays employee information: ID, name, department, position, location, HR status, link status
- Action buttons: Link, Create User, Unlink

## Key Features

### Data Synchronization
- One-click sync from HR4 system
- Upsert logic prevents duplicates
- Stores complete employee record including raw JSON
- Audit logging for all sync operations

### Linking System
- Optional 1:1 mapping between CoreT2 users and HR4 employees
- Prevents duplicate links (one user = one employee)
- Visual indicators for link status
- Easy unlink functionality

### User Creation from Employee Data
- Pre-fills user creation form with employee information
- Suggests username based on employee name and ID
- Admin retains full control over role and password
- Uses existing user creation workflow

### Security & Permissions
- All HR4 endpoints require Super Admin access
- API key stored server-side (not exposed to client)
- Audit logging for all HR4 operations
- Input validation and error handling

### Data Integrity
- HR4 data is read-only (no writes back to HR4)
- CoreT2 user management fields are never overwritten
- Existing user actions remain intact
- Link/unlink operations don't affect user accounts

## Database Schema

### hr4_employees Table
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- hr4_employee_id (VARCHAR(30), UNIQUE, NOT NULL)
- hr4_internal_id (INT, NULL)
- full_name (VARCHAR(200))
- email (VARCHAR(200))
- phone (VARCHAR(30))
- department (VARCHAR(120))
- job_title (VARCHAR(160))
- work_location (VARCHAR(120))
- employment_status (VARCHAR(50))
- employment_type (VARCHAR(50))
- hr_status (VARCHAR(30))
- hr_updated_at (DATETIME, NULL)
- raw_json (LONGTEXT)
- synced_at (DATETIME, NOT NULL)
```

### user_hr4_link Table
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- user_id (INT, UNIQUE, NOT NULL)
- hr4_employee_id (VARCHAR(30), UNIQUE, NOT NULL)
- linked_at (DATETIME, NOT NULL)
```

## API Integration

### HR4 API Endpoint
- URL: `https://hr4.microfinancial-1.com/allemployees`
- Method: GET
- Authentication: X-api-key header
- Response: JSON with status and data array

### Expected Response Format
```json
{
  "status": "success",
  "data": [
    {
      "employee_id": "EMP-001",
      "id": 123,
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "work_location": "Main Office",
      "employment_status": "Full-time",
      "employment_type": "Permanent",
      "status": "Active",
      "position": {
        "department": "IT"
      },
      "job": {
        "job_title": "Developer"
      },
      "updated_at": "2026-02-13 10:00:00"
    }
  ]
}
```

## Configuration Required

### API Key Setup
Update `classes/HR4EmployeeClient.php` line 4:
```php
private $apiKey = 'YOUR_ACTUAL_HR4_API_KEY_HERE';
```

Or better yet, add to `initialize.php`:
```php
define('HR4_API_KEY', 'your_api_key_here');
```

Then update HR4EmployeeClient.php constructor to use it.

## Usage Workflow

### Initial Setup
1. Run `setup_hr4_tables.php` to create database tables
2. Configure HR4 API key in `HR4EmployeeClient.php`
3. Access User Management page as Super Admin

### Syncing Employees
1. Click "Sync with HR4" button
2. System fetches all employees from HR4
3. Data is upserted into `hr4_employees` table
4. Success message shows number of employees synced

### Linking Employees to Users
1. View HR4 Employee Directory table
2. For unlinked employees, click the link icon
3. Select a CoreT2 user from the dropdown (only shows unlinked users)
4. Click "Create Link"
5. Employee now shows as "Linked" with username

### Creating Users from Employees
1. For unlinked employees, click the user-plus icon
2. User creation form opens with pre-filled data
3. Admin sets role and password
4. Save to create new CoreT2 user
5. Optionally link the new user to the employee

### Unlinking
1. For linked employees, click the unlink icon
2. Confirm the action
3. Link is removed (user account remains unchanged)

## Audit Trail

All HR4 operations are logged to the `audit_trial` table:
- Sync operations: "Sync HR4 Employees"
- Link operations: "Link HR4 Employee"
- Unlink operations: "Unlink HR4 Employee"

Each log entry includes:
- User ID (who performed the action)
- Action type
- Module: "User Management"
- Reference ID (user_id for link/unlink)
- Details (descriptive message)
- IP address and timestamp

## Testing Checklist

- [ ] Database tables created successfully
- [ ] HR4 API key configured
- [ ] Sync button fetches and stores employee data
- [ ] Employee directory displays correctly
- [ ] Search/filter works in employee directory
- [ ] Link modal shows only unlinked users
- [ ] Linking creates proper association
- [ ] Duplicate link attempts are prevented
- [ ] Unlinking removes association
- [ ] Create user from employee pre-fills form
- [ ] Audit logs are generated for all actions
- [ ] Super Admin access control enforced
- [ ] Error handling works for API failures

## Future Enhancements

- Scheduled automatic syncing (cron job)
- Sync status indicator (last sync time)
- Bulk linking functionality
- Employee status change notifications
- Integration with profile photos from HR4
- Department-based filtering
- Export employee directory to CSV/PDF

## Notes

- HR4 is a read-only data source
- Linking is optional and doesn't affect user functionality
- Unlinked employees can still be viewed in the directory
- The system supports employees without email addresses
- Raw JSON is stored for future extensibility
