# Time Tracking Plugin for Tutor LMS

## Overview
This plugin tracks the time users spend on your WordPress website, specifically integrating with the Tutor LMS plugin. It records session durations, providing insights into user engagement with your online courses.

## Features
- Tracks time spent by logged-in users and guests.
- Uses AJAX to update session durations in real time.
- Stores data in a custom WordPress database table.
- Provides an admin panel for viewing user session durations.

## Technologies Used
- **WordPress Hooks & Actions**: To integrate with WordPress and Tutor LMS.
- **PHP & MySQL**: For backend development and data storage.
- **JavaScript (AJAX & jQuery)**: To track and update time dynamically.
- **Tutor LMS Compatibility**: Works alongside Tutor LMS to track course session times.

## Installation
1. **Upload the Plugin:**
   - Upload the plugin folder to the `/wp-content/plugins/` directory.
   - Alternatively, install it via the WordPress plugin uploader.

2. **Activate the Plugin:**
   - Go to **Plugins** > **Installed Plugins** in WordPress.
   - Find "Time Tracking Plugin" and click **Activate**.

3. **Database Setup:**
   - The plugin automatically creates a database table upon activation.

## Integration with Tutor LMS
This plugin is designed to work seamlessly with **Tutor LMS** to track how long students spend on course pages. To integrate it:

1. Ensure **Tutor LMS** is installed and activated.
2. The plugin will automatically start tracking time on all course and lesson pages.
3. To view reports, navigate to **Admin Dashboard > Time Tracking**.

## Usage
- The plugin sends an AJAX request every 30 seconds to update session duration.
- Session details can be retrieved from the custom database table (`wp_time_tracking`).
- Admins can view user time logs in a dedicated dashboard section.

## Uninstallation
- Deactivating the plugin stops time tracking but retains data.
- To remove all data, delete the plugin and manually drop the `wp_time_tracking` table.

## Future Enhancements
- Improved reporting dashboard.
- Export session data as CSV.
- User-specific session analysis.

For any issues or feature requests, feel free to contribute or report on [GitHub Repository Link].

