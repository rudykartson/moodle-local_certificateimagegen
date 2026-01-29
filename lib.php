<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * @package     local_certimagegen
 * @copyright   2026 Rudraksh Batra <batra.rudraksh@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Pluginfile callback for local_certimagegen
 *
 * @param stdClass $course Not used but required by signature.
 * @param stdClass $cm Not used but required by signature.
 * @param context $context The context from the URL.
 * @param string $filearea The file area from the URL.
 * @param array $args Remaining arguments (itemid, path, filename).
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool false if file not found or not allowed, nothing if success (send_stored_file handles it).
 */
function local_certimagegen_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'content' && $filearea !== 'defaultcertimage') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_certimagegen', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    // Serve the file
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function xmldb_local_certimagegen_pre_install() {
    $pdftoppmPath = trim(shell_exec('command -v pdftoppm 2>/dev/null'));
    if (empty($pdftoppmPath)) {
        throw new \moodle_exception(
            'pdftoppm_missing', // language string identifier
            'local_certimagegen', // plugin name
            '', // optional link
            'Please install pdftoppm before installing this plugin.'
        );
    }
}

