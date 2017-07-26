# ispconfig3-website-git #
Currently requires chroot (i.e. jailkit) for the shell users!

## Installation ##
To install, simply copy the website_git.inc.php to `/usr/local/ispconfig/server/plugins-available/` and symlink it to the plugins-enabled directory. Or just copy/paste the commands below.
```
git clone https://github.com/rynoxx/ispconfig3-website-git
cp ispconfig3-website-git/website_git.inc.php /usr/local/ispconfig/server/plugins-available/
ln -s /usr/local/ispconfig/server/plugins-available/website_git.inc.php /usr/local/ispconfig/server/plugins-enabled/
```
