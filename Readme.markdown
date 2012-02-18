PHPUnit_TicketListener_Redmine
===============================

Closing and reopening Google Code issues via PHPUnit tests.

Inspired by Raphael Stolt's article [Closing and reopening GitHub issues via PHPUnit tests](http://raphaelstolt.blogspot.com/2010/01/closing-and-reopening-github-issues-via.html). Read this article about how to use PHPUnit's ticket listeners.

Example configuration:
---------------------------------
    <phpunit>
	<listeners>
	    <listener class="PHPUnit_Extensions_TicketListener_Redmine" 
                 file="classes/pear/PHPUnit/Extensions/TicketListener/Redmine.php">
    		<arguments>
    		    <string>http://redmine.example.org</string><!-- redmine location -->
    		    <string>63d802d3d60069040f8fb0ea08b07d66</string><!-- your api-key for redmine -->
    		    <array>
        		<element key="0">
            		    <integer>5</integer><!-- redmine's id of issue's status 'closed', default 5 - closed -->
        		</element>
        		<element key="1">
            		    <integer>6</integer><!-- redmine's id of status that also closes issue (for example - canceled) -->
        		</element>
    		    </array>
    		    <integer>2</integer><!-- id of issue's status 'open', default 2 - assigned -->
    		    <integer>2</integer><!-- id of issue's status 'reopen', default 2 - assigned  -->
    		</arguments>
	    </listener>
	</listeners>
    </phpunit>
