1.After cloning the repository,run composer install
2.create a database named event_reminder and update the database credentials in the .env file
3.run php artisan migrate
4.for sending emails,run php artisan queue:work and then  run php artisan events:email
