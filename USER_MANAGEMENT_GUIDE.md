# User Management System

A complete user management solution for your Filament application with both web interface and command-line tools.

## Features

✅ **Web Interface (Filament Admin Panel)**
- Add new users with name, email, and password
- Edit existing user information
- Delete users with confirmation
- Search and filter users
- Responsive design

✅ **Command Line Tools**
- List all users
- Create users via batch script
- Delete users via batch script
- Quick access to admin panel

## Current Users

You currently have **5 users** in your database:

1. **Admin User** (admin@teltonika-monitor.local) - Created: 2025-09-12
2. **asifremanbd@gmail.com** (asifremanbd@gmail.com) - Created: 2025-09-15
3. **Admin User** (admin@example.com) - Created: 2025-09-16
4. **Dan Temple** (dan@dantemple.co.uk) - Created: 2025-09-30
5. **Gerard Wackrow** (gerardwackrow@gmail.com) - Created: 2025-09-30

## How to Use

### Web Interface (Recommended)

1. **Start your Laravel server:**
   ```bash
   cd filament-app
   php artisan serve
   ```

2. **Access the admin panel:**
   - Open your browser and go to: `http://localhost:8000/admin`
   - Login with any existing user credentials
   - Navigate to "Users" in the sidebar under "Administration"

3. **Manage users:**
   - **Add User:** Click "Add New User" button
   - **Edit User:** Click the edit icon next to any user
   - **Delete User:** Click the delete icon (requires confirmation)

### Command Line Interface

1. **Run the user management script:**
   ```bash
   user-management.bat
   ```

2. **Available options:**
   - List all users
   - Create new user (interactive)
   - Delete user by ID
   - Open admin panel in browser

### Manual Commands

**List users:**
```bash
cd filament-app
php list-users.php
```

**Create user via Tinker:**
```bash
cd filament-app
php artisan tinker
>>> $user = new App\Models\User();
>>> $user->name = 'John Doe';
>>> $user->email = 'john@example.com';
>>> $user->password = Hash::make('password123');
>>> $user->save();
```

## Security Features

- **Password Hashing:** All passwords are automatically hashed using Laravel's Hash facade
- **Email Validation:** Email addresses are validated and must be unique
- **Confirmation Dialogs:** Delete actions require confirmation
- **Form Validation:** All fields are properly validated

## File Structure

```
├── filament-app/app/Filament/Resources/
│   ├── UserResource.php                    # Main resource definition
│   └── UserResource/Pages/
│       ├── ListUsers.php                   # User listing page
│       ├── CreateUser.php                  # User creation page
│       └── EditUser.php                    # User editing page
├── filament-app/list-users.php             # Command-line user listing
├── user-management.bat                     # Interactive management script
└── USER_MANAGEMENT_GUIDE.md               # This guide
```

## Navigation

The user management system is automatically added to your Filament admin panel under:
- **Navigation Group:** Administration
- **Icon:** Users icon
- **Sort Order:** 1 (appears first in Administration group)

## Next Steps

1. **Test the system:** Run `user-management.bat` and try creating a test user
2. **Access via web:** Start your server and visit the admin panel
3. **Customize:** Modify the UserResource.php file to add additional fields if needed
4. **Security:** Consider adding role-based permissions for production use

## Troubleshooting

**If users don't appear in Filament:**
- Make sure your Laravel server is running
- Clear cache: `php artisan cache:clear`
- Check that you're logged in to the admin panel

**If batch script doesn't work:**
- Make sure you're in the project root directory
- Ensure PHP is in your system PATH
- Check that the filament-app directory exists

**For permission errors:**
- Run as administrator if needed
- Check file permissions on Windows