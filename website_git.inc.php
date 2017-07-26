<?php
/* @TODO Add a license*/

class website_git {
	/**
	 * The internal plugin name
	 * @var string
	 */
	var $plugin_name = 'website_git';

	/**
	 * The internal class name
	 * @var string
	 */
	var $class_name = 'website_git';

	var $action = '';

	/**
	 * Called during ISPConfig installation. Determines whether or not the plugin should be enabled by default.
	 *
	 * @return bool Whether or not it should be enabled (always true)
	 */
	function onInstall(){
		return true;
	}

	/**
	 * Runs when the plugin is loaded
	 */
	function onLoad() {
		global $app;

		$app->plugins->registerEvent('web_domain_insert', $this->plugin_name, 'insert');
		$app->plugins->registerEvent('web_domain_update', $this->plugin_name, 'update');
	}

	/**
	 * Runs when a new site is added, creates the needed directories and files for the git repo
	 */
	function insert($event_name, $data) {
		$this->update($event_name, $data);
	}

	function update($event_name, $data) {
		global $app, $conf;

		/*
		$app->uses('getconf');
		$web_config = $app->getconf->get_server_config($conf['server_id'], 'web');
		*/

		$username = escapeshellcmd($data['new']['system_user']);
		$groupname = escapeshellcmd($data['new']['system_group']);

		$gitBinary = $this->_exec("which git"); //"/usr/bin/git";

		$gitHook = <<<EOS
#!/bin/bash
while read oldrev newrev refname
do
	if [[ \$refname =~ .*/master\$ ]];
	then
		echo "Master received.  Deploying master branch to production..."
		git --work-tree=/web --git-dir=/website.git checkout -f
	else
		echo "Ref \$refname successfully received.  Doing nothing: only the master branch may be deployed on this server."
	fi
done
EOS;

		$gitIgnore = <<<EOS
# -----------------------------------------------------------------
# .gitignore
# Bare Minimum Git
# http://ironco.de/bare-minimum-git/
# ver 20170502
#
# From the root of your project run
# curl -O https://gist.githubusercontent.com/salcode/10017553/raw/.gitignore
# to download this file
#
# This file is tailored for a general web project, it
# is NOT optimized for a WordPress project.  See
# https://gist.github.com/salcode/b515f520d3f8207ecd04
# for a WordPress specific .gitignore
#
# This file specifies intentionally untracked files to ignore
# http://git-scm.com/docs/gitignore
#
# NOTES:
# The purpose of gitignore files is to ensure that certain files not
# tracked by Git remain untracked.
#
# To ignore uncommitted changes in a file that is already tracked,
# use `git update-index --assume-unchanged`.
#
# To stop tracking a file that is currently tracked,
# use `git rm --cached`
#
# Change Log:
# 20170726 Ignore stats/
# 20170502 unignore composer.lock
# 20170502 ignore components loaded via Bower
# 20150326 ignore jekyll build directory `/_site`
# 20150324 Reorganized file to list ignores first and whitelisted last,
#          change WordPress .gitignore link to preferred gist,
#          add curl line for quick installation
#          ignore composer files (vendor directory and lock file)
# 20140606 Add .editorconfig as a tracked file
# 20140418 remove explicit inclusion
#          of readme.md (this is not an ignored file by default)
# 20140407 Initially Published
#
# -----------------------------------------------------------------

# ignore all files starting with . or ~
.*
~*

# ignore node/grunt dependency directories
node_modules/

# ignore composer vendor directory
/vendor

# ignore components loaded via Bower
/bower_components

# ignore jekyll build directory
/_site

#ignore stats directory
stats/

# ignore OS generated files
ehthumbs.db
Thumbs.db

# ignore Editor files
*.sublime-project
*.sublime-workspace
*.komodoproject

# ignore log files and databases
*.log
*.sql
*.sqlite

# ignore compiled files
*.com
*.class
*.dll
*.exe
*.o
*.so

# ignore packaged files
*.7z
*.dmg
*.gz
*.iso
*.jar
*.rar
*.tar
*.zip

# -------------------------
# BEGIN Whitelisted Files
# -------------------------

# track these files, if they exist
!.gitignore
!.editorconfig
!README.md
!CHANGELOG.md
!composer.json

# track favicon files, if they exist
!android-chrome-*.png
!apple-touch-icon*.png
!browserconfig.xml
!favicon*.png
!favicon*.ico
!manifest.json
!mstile-*.png
!safari-pinned-tab.svg
EOS;

		$docroot = "/" . trim($data['new']['document_root'], "/");

		$app->system->web_folder_protection($data['new']['document_root'],false);

		if(!@is_dir($docroot.'/website.git')){
			$this->_exec($gitBinary . ' --bare init ' . escapeshellarg($docroot . "/website.git") . '');
			$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' config user.name "' . $username . '"');
			$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' config user.email "' . $username . "@" . $groupname . '"');

			$app->system->mkdirpath($docroot.'/website.git/hooks', 0711, $username, $groupname);

			$app->system->file_put_contents($docroot.'/web/.gitignore', $gitIgnore);
			$app->system->chmod($docroot.'/web/.gitignore', 0755);
			$app->system->chown($docroot.'/web/.gitignore', $username);
			$app->system->chgrp($docroot.'/web/.gitignore', $groupname);

			$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' add -A');
			$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' commit -m "Initial commit"');
			//$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' config core.worktree "/web"');
		}

		$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' config user.name "' . escapeshellarg($username) . '"');
		$this->_exec($gitBinary . ' --work-tree=' . escapeshellarg($docroot . "/web") . ' --git-dir=' . escapeshellarg($docroot . "/website.git") . ' config user.email "' . escapeshellarg($username . "@" . $groupname) . '"');

		$app->system->file_put_contents($docroot.'/website.git/hooks/post-receive', $gitHook);
		$app->system->chmod($docroot.'/website.git/hooks/post-receive', 0755);
		$app->system->chown($docroot.'/website.git/hooks/post-receive', $username);
		$app->system->chgrp($docroot.'/website.git/hooks/post-receive', $groupname);

		$this->_exec("chown " . escapeshellarg($username) . ":" . escapeshellarg($groupname) . " " . escapeshellarg($docroot . "/website.git") . " -R");

		$app->system->web_folder_protection($data['new']['document_root'],true);
	}

	private function _exec($command) {
		global $app;

		$app->log('exec: '. $command, LOGLEVEL_DEBUG);

		return exec($command);
	}
}
