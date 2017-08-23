# ispconfig3-website-git #
**Currently requires chroot (i.e. jailkit) for the shell users!**  
This plugin creates a bare git repository for the web directory to allow clients to change their websites using only git.

## Installation ##
To install, simply copy the website_git.inc.php to `/usr/local/ispconfig/server/plugins-available/` and symlink it to the plugins-enabled directory. Or just copy/paste the commands below, you will need root or sudo to run them.
```
git clone https://github.com/rynoxx/ispconfig3-website-git
ln -s `pwd`/ispconfig3-website-git/website_git.inc.php /usr/local/ispconfig/server/plugins-available/
ln -s /usr/local/ispconfig/server/plugins-available/website_git.inc.php /usr/local/ispconfig/server/plugins-enabled/
```

To update, simply `cd` into the isp-config3-website-git repository you've cloned and `git pull`.

If you want to allow ISPConfig shell users to log in using passwords, while keeping other users with the settings defined in `/etc/ssh/sshd_config` add this to the bottom of the `/etc/ssh/sshd_config` file:  
```
# Allow ISPConfig shell users to login using password
Match Group client*,!root,!sudo
	PasswordAuthentication yes
```

This ensures that ssh users in groups beginning with `client` (`clientX` being the default group for ISPConfig) are allowed to login using password, while making sure that the setting doesn't override your other settings for users in the root and sudo groups.  
And don't forget to restart the SSHD service (`service sshd restart`) after saving the file.  
More thorough information about configuring `Match` in sshd_config available [**here**](https://security.stackexchange.com/questions/18036/creating-user-specific-authentication-methods-in-ssh/18038#18038) and [**here**](https://linux.die.net/man/5/sshd_config).

## Usage ##

1. Create a site in ISPConfig and create a shell user attached to it.
2. Clone the repository on whichever computer you want to work on the website files on.  
	The git repository is located at `<username>@<hostname>:/website.git` the hostname can be either the hostname of the ISPConfig server or any other hostname pointing towards it. e.g. `git clone user1@example.com:/website.git`.  

	I recommend using a specific name when you clone the repository, e.g. `git clone user1@example.com:/website.git example.com` would clone it into the `example.com` directory rather than a directory called `website`
3. Edit the files, and create a new commit and simply push them using `git push`. Do note that **ONLY** the master branch gets updated on the server, pushing other branches does nothing.
4. That's it! Go to the website and view the new changes you've made :tada:  
	_And don't forget to `git pull` if you're working on the same website from multiple devices._
