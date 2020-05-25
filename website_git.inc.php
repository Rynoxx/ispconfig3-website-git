<?php
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

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

		$docroot = "/" . trim($data['new']['document_root'], "/");

		$gitBinary = $this->_exec("which git"); //"/usr/bin/git";

		$gitHook = <<<EOS
#!/bin/bash
while read oldrev newrev refname
do
	if [[ \$refname =~ .*/master\$ ]];
	then
		echo "Master received.  Deploying master branch to production..."
		git --work-tree=$docroot/web --git-dir=$docroot/website.git checkout -f
	else
		echo "Ref \$refname successfully received.  Doing nothing: only the master branch may be deployed on this server."
	fi
done
EOS;

		$gitIgnore = <<<EOS
stats/

thumbs.db
._*
*.DS_Store
EOS;

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

		if(!@is_dir($docroot . $docroot)){
			$app->system->mkdirpath($docroot . $docroot, 0755, 'root', 'root');
			$app->system->create_relative_link($docroot . '/web', $docroot . $docroot . '/web');
			$app->system->create_relative_link($docroot . '/website.git', $docroot . $docroot . '/website.git');
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
