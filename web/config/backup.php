<?php

return [
    // Full path to mysqldump (the XAMPP one on this server).
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'D:\\xampp\\mysql\\bin\\mysqldump.exe'),

    // Daily zipped dumps older than this many days are deleted.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 3),

    // True only on the offline backup server. backup:import refuses to run otherwise, because
    // importing into the live database would bring cleared rows back.
    'is_backup_server' => (bool) env('BACKUP_IS_BACKUP_SERVER', false),

    // When the accountant (or manager) has confirmed every sale and supplement of a date, a dump of the database is queued: it holds
    // the day as it was reviewed.
    'dump_on_confirmation' => (bool) env('BACKUP_DUMP_ON_CONFIRMATION', true),

    // The nightly 02:00 dump only runs when the newest dump is older than this many hours (a day nobody confirms).
    'safety_net_hours' => (int) env('BACKUP_SAFETY_NET_HOURS', 20),

    // Run backup:dump right before a backup clears anything.
    'dump_before_clear' => true,

    // Under storage/app (the end-to-end tests point them somewhere else so the real dumps are never touched).
    'dump_dir' => env('BACKUP_DUMP_DIR', 'backups/daily'),
    'export_dir' => env('BACKUP_EXPORT_DIR', 'backups/exports'),
];
