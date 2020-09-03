# About Entry

The file named "**api.php.example**" in the example folder (doc/example) of NervSys is our suggested entry script, which can be directly use in your project, or slightly modified if needed.  

### usage

* Rename the file to any filename, and change the extension to .php. Example: myEntry.php
* Copy the file above to your own web public directory, or, just leave it under project root. Remember that, web public directory should be put under the same directory of NervSys, no matter if NervSys is under a sub-folder.
* Modify "ns.php" require path to your own.
* All custom router and custom output handler can be register/set right in entry script, and your own code logic as well. But we suggest to use "app.ini" to set "INIT"/"CALL" to control them.
* Done