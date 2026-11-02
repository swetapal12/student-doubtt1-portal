STUDENT DOUBT PORTAL — PHP + MYSQL
===================================

FREE TOOLS
- XAMPP for Windows (PHP, Apache, MySQL/MariaDB): https://www.apachefriends.org/
- A web browser
- No paid software or hosting needed to run locally

QUICK START
1. Extract the ZIP.
2. Double-click RUN_PORTAL.bat.
3. The launcher checks common XAMPP folders.
4. If XAMPP is missing, it attempts to download the Windows installer from the XAMPP SourceForge project and opens the installer. Complete the installer wizard (recommended location: C:\xampp), then run RUN_PORTAL.bat again.
5. If XAMPP is already installed in a custom folder, enter that folder path when prompted.
6. The launcher copies the portal files to XAMPP htdocs if needed, starts Apache/MySQL, then opens:
   http://localhost/Student_Doubt_Portal/

DATABASE
- The database and tables are created automatically when the page is opened for the first time.
- The app expects the default XAMPP MySQL user root with a blank password.

DEMO TEACHER LOGIN
Email: teacher@portal.local
Password: Teacher@123

STUDENTS
Register a student account from the website.

FEATURES
- Student registration and login
- Password hashing
- Submit doubts by subject
- Student dashboard and status counts
- Teacher dashboard and answers
- Pending/Answered status
- Responsive layout

TROUBLESHOOTING
- If Apache or MySQL cannot start, open XAMPP Control Panel and start them manually.
- If port 80 or 3306 is already in use, resolve the port conflict in XAMPP.
- If the automatic installer download fails, download XAMPP manually from https://www.apachefriends.org/ and run this BAT file again.
- Downloading and installing XAMPP requires an internet connection and may require Windows permission prompts.
- This launcher cannot silently complete the XAMPP installation; you must complete the official installer wizard.

SECURITY NOTE
This is a local college-project starter, not production-hardened. Before public deployment, change demo credentials and add appropriate production security (HTTPS, CSRF protection, secure session settings, and server configuration).
