# dump1090-mongodb
Records all ADS-B/TIS-B/UAT messages from dump1090/dump978 into mongodb

This assumes you already have mongodb running on your local system and PHP with the mongodb extension.

Run "composer install", then edit the URLs at the top of adsb.php to point to your instances of dump1090/dump978.

You can run this from cron, if it's already running it won't run a second copy.


Features:

* Both dump1090 and dump978 messages will get logged
* It polls for new messages every second, but doesn't insert duplicate messages into mongo
* Position is saved as a 2dsphere index to make distance calculation queries easy.
