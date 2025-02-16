1.After cloning the repository,run composer install
2.create a database named event_reminder and update the database credentials in the .env file
3.run php artisan migrate
4.for sending emails,run php artisan queue:work and then  run php artisan events:email
6.you should use your SMTP of Mailtrap.io to check whether the mail is going to the event participants;i've used my Mailtrap.io email
credential to implement it;use your smtp credential in local .env and check your mailtrap.io inboxesto check sent email.
