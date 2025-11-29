# Aanchal Vihar Election Application

## Project Overview

This is a Resident Welfare Association (RWA) election tracking web application designed for the Aanchal Vihar community. Built using PHP and SQLite, its primary purpose is to manage the various stages of an election process, from member registration and verification to candidate nomination and approval, all secured with robust role-based access control.

The application is built with a focus on ease of use, maintainability, and responsiveness, ensuring a seamless experience across various devices.

## Key Features

*   **User and Role Management:** Defines distinct user roles (`data_entry`, `accounts`, `returning_officer`, `super_admin`, `voter`) with tailored permissions for secure and controlled access.
*   **Member Registration:** Allows 'data_entry' and 'super_admin' roles, as well as self-registration for new residents, to register RWA members, including mandatory profile images and Aadhaar card uploads.
*   **Responsive Design:** Features a mobile-first approach with a responsive header, navigation (hamburger menu on mobile), and dynamic tables that adapt gracefully to different screen sizes.
*   **Centralized Member Management:** Provides 'super_admin' and 'returning_officer' roles with comprehensive tools to view, edit, soft-delete (mark as inactive), and export member data.
*   **Streamlined Payment Verification:** The 'accounts' team can efficiently manage payment statuses, update receipt numbers, and approve/reject members for financial compliance.
*   **Document Verification:** 'returning_officer' and 'super_admin' can review and approve/reject member documents.
*   **Candidate Nomination & Approval Workflow:**
    *   Authorized users can nominate qualified voters for various office-bearer positions.
    *   A dedicated "Verify Nominations" module allows 'returning_officer' and 'super_admin' to approve or reject submitted nominations.
*   **Audit Trail:** Logs significant actions and changes within the system for transparency and accountability.
*   **Modular Design:** Utilizes shared header and footer components for consistent UI and easier maintenance.
*   **Caching:** Implements file-based caching for frequently accessed, less volatile data to improve performance.

## Technologies Used

*   **Backend:** PHP (with PDO for database interaction)
*   **Database:** SQLite (election.db)
*   **Frontend:** HTML, CSS, JavaScript, jQuery
*   **Libraries:** DataTables.js (for enhanced table features like search, pagination, sorting)

## Getting Started

To set up and run this project locally, follow these steps:

### Prerequisites

*   **PHP:** Installed (with PDO SQLite extension enabled).
*   **Web Server:** A web server (e.g., Apache, Nginx) or PHP's built-in development server.
*   **Directory Permissions:** Ensure the `cache/` and `uploads/` directories (located at the project root) are writable by the web server.

### Installation

1.  **Clone the Repository:**
    ```bash
    git clone https://github.com/arora200/rwa-elections-.git
    cd rwa-elections-
    ```

2.  **Database Setup:**
    *   Create the SQLite database and tables:
        ```bash
        php setup_db.php
        ```
    *   Insert dummy data (highly recommended for testing all roles and workflows):
        ```bash
        php populate_full_dummy_data.php
        ```
        *(Note: `populate_full_dummy_data.php` is created during initial setup and typically deleted afterwards. If missing, regenerate from the steps in `GEMINI.md` or manually add users/members.)*

### Running the Application

1.  **Start the PHP Development Server:**
    ```bash
    php -S localhost:8000
    ```

2.  **Access the Application:**
    *   Open your web browser and go to `http://localhost:8000/`.
    *   You will be redirected to the login page (`index.php`).

### Dummy User Credentials (for testing)

If you used `populate_full_dummy_data.php`, you can log in with these credentials (password for all is `password`):

*   **Super Admin:** `superadmin`
*   **Data Entry:** `dataentry`
*   **Accounts:** `accounts`
*   **Returning Officer:** `rofficer`

## Contribution

We welcome contributions to the Aanchal Vihar Election Application! Please refer to our [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on how to get started.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
