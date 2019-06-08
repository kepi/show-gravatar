<?php
/**
 * Show gravatar for sender
 *
 * This plugin will show gravatar picture for message sender.
 *
 * Copyright (C) 2009 Ondřej Kudlík https://kepi.cz
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *
 * Enable the plugin in config/main.inc.php
 *
 * @version 0.3
 * @author Ondra Kudlík (Kepi)
 * @website https://kepi.cz/
 */

class show_gravatar extends rcube_plugin
{
    public $task = 'mail|settings|addressbook';

    private $gravatar_id;
    private $sender;
    private $rcmail;
    private $size;
    private $rating;
    private $default;
    private $border;
    private $default_rating = 'g';
    private $default_default = 'mp';
    private $gravatar_url = 'https://secure.gravatar.com/';

    function init()
    {
        $this->add_texts('localization/', false);

        $this->rcmail = rcmail::get_instance();

        // display photo in message preview or addressbook
        if (
            $this->rcmail->task == 'addressbook' &&
            ($this->rcmail->action == 'photo' ||
                $this->rcmail->action == 'show')
        ) {
            // in addressbook, there is larger format then in message
            $this->size = $this->rcmail->action == 'show' ? 112 : 32;

            // use dedicated hook to show contact photos
            $this->add_hook('contact_photo', array($this, 'contact_photo'));
        }
        // settings page
        elseif ($this->rcmail->task == 'settings') {
            $dont_override = $this->rcmail->config->get(
                'dont_override',
                array()
            );
            if (!in_array('show_gravatar', $dont_override)) {
                $this->add_hook('preferences_list', array(
                    $this,
                    'prefs_table'
                ));
                $this->add_hook('preferences_save', array($this, 'save_prefs'));
            }
        }
    }

    function enabled($option)
    {
        return $this->rcmail->config->get($option) ? true : false;
    }

    // check if array is associative
    function is_assoc($array)
    {
        return is_array($array) &&
            (0 !==
                count(array_diff_key($array, array_keys(array_keys($array)))) ||
                count($array) == 0);
    }

    // helper for select
    function select($option, $possible_options, $default, &$options)
    {
        $value = $this->rcmail->config->get($option, $default);
        $select = new html_select(array('name' => '_' . $option));

        // if associative, build needed arrays
        if ($this->is_assoc($possible_options)) {
            $opt_labels = array();
            $opt_attrs = array();

            foreach ($possible_options as $attr => $label) {
                $opt_labels[] = $label;
                $opt_attrs[] = (string) $attr;
            }

            $select->add($opt_labels, $opt_attrs);

            // else options and attrs are same
        } else {
            $select->add($possible_options, $possible_options);
        }

        $options[$option] = array(
            'title' => html::label($option, rcube::Q($this->gettext($option))),
            'content' => $select->show($value)
        );
    }

    function prefs_table($args)
    {
        $options = array();

        $this->select(
            'gravatar_default',
            array(
                'mp' => rcube::Q($this->gettext('mp')),
                'identicon' => rcube::Q($this->gettext('identicon')),
                'monsterid' => rcube::Q($this->gettext('monsterid')),
                'wavatar' => rcube::Q($this->gettext('wavatar')),
                'retro' => rcube::Q($this->gettext('retro')),
                'robohash' => rcube::Q($this->gettext('robohash'))
            ),
            $this->default_default,
            $options
        );
        $this->select(
            'gravatar_rating',
            array(
                'g' => rcube::Q($this->gettext('gravatar_G')),
                'pg' => rcube::Q($this->gettext('gravatar_PG')),
                'r' => rcube::Q($this->gettext('gravatar_R')),
                'x' => rcube::Q($this->gettext('gravatar_X'))
            ),
            $this->default_rating,
            $options
        );

        if ($args['section'] == 'mailview') {
            $args['blocks']['gravatar'] = array(
                'name' => rcube::Q($this->gettext('gravatars')),
                'options' => $options
            );
        }

        return $args;
    }

    /**
     * Handler for preferences_save hook.
     * Executed on MailView settings form submit.
     *
     * @param array Original parameters
     *
     * @return array Modified parameters
     */
    function save_prefs($p)
    {
        if ($p['section'] == 'mailview') {
            $p['prefs'] = array(
                'gravatar_default' => rcube_utils::get_input_value(
                    '_gravatar_default',
                    rcube_utils::INPUT_POST
                ),
                'gravatar_rating' => rcube_utils::get_input_value(
                    '_gravatar_rating',
                    rcube_utils::INPUT_POST
                )
            );
        }
        return $p;
    }

    // FIXME pokud je record['photo'] tak chceme asi radsi to
    function contact_photo($p)
    {
        if (!$p['data']) {
            // TODO: try for every email address of contact record?
            $emails = rcube_addressbook::get_col_values(
                'email',
                $p['record'],
                true
            );
            $email = $p['email'] ? $p['email'] : $emails[0];

            if ($email) {
                $this->gravatar_id = md5(strtolower(trim($email)));
                $p['url'] = $this->gravatar_url();
            }
        }
        return $p;
    }

    function gravatar_url()
    {
        $rating = $this->rcmail->config->get(
            'gravatar_rating',
            $this->default_rating
        );

        $default = $this->rcmail->config->get(
            'gravatar_default',
            $this->default_default
        );

        return $this->gravatar_url .
            "avatar/" .
            $this->gravatar_id .
            "?s=" .
            $this->size .
            "&r=" .
            $rating .
            "&d=" .
            $default;
    }
}
