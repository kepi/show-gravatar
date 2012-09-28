<?php

/**
 * Show gravatar for sender
 *
 * This plugin will show gravatar picture for message sender.
 *
 * Enable the plugin in config/main.inc.php
 *
 * @version 0.9
 * @author Ondra 'Kepi' Kudlik
 * @website http://kepi.cz/
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
  private $default_size = 48;
  private $default_rating = 'g';
  private $default_default = 'identicon';
  private $gravatar_port = 80;
  private $gravatar_hostname = 'www.gravatar.com';
  private $gravatar_url = 'http://www.gravatar.com/';
  private $nativepics = false;

  function init()
  {
    $this->add_texts('localization/', false);

    $this->rcmail = rcmail::get_instance();
    $this->nativepics = !version_compare(RCMAIL_VERSION, '0.9-x', '<');  // Roundcube >= 0.9 has native support for contact pics in mail view

    if ( $this->is_https() ) {
      $this->gravatar_port = 443;
      $this->gravatar_hostname = 'secure.gravatar.com';
      $this->gravatar_url = 'https://secure.gravatar.com/';
    }
    
    // render gravatar picture directly into email preview in pre-0.9 versions
    if ($this->rcmail->task == 'mail'
          && !$this->nativepics
          && ( ($this->enabled('gravatar_enable_message') && $this->rcmail->action == 'show')
            || ($this->enabled('gravatar_enable_preview') && $this->rcmail->action == 'preview'))) {

      $this->add_hook('render_page', array($this, 'render_page'));
      $this->add_hook('message_load', array($this, 'message_load'));
      $this->add_hook('template_object_messageheaders', array($this, 'html_output'));

      $this->size = $this->rcmail->config->get('gravatar_size', $this->default_size);
      $this->rating = $this->rcmail->config->get('gravatar_rating', $this->default_rating);
      $this->default = $this->rcmail->config->get('gravatar_default', $this->default_default);

      $this->border = $this->enabled('gravatar_border');

      $skin = $this->rcmail->config->get('skin');
      if (!file_exists($this->home."/skins/$skin/show_gravatar.css"))
        $skin = 'default';

      // add style for placing gravatar icon
      $this->include_stylesheet("skins/$skin/show_gravatar_".$this->size.".css");
    }
    // use native support for contact photos
    else if ($this->rcmail->task == 'addressbook' && ($this->rcmail->action == 'show' || $this->rcmail->action == 'photo')) {
      $this->size = $this->rcmail->config->get('gravatar_size', $this->default_size);
      $this->rating = $this->rcmail->config->get('gravatar_rating', $this->default_rating);
      $this->default = '404';

      // use dedicated hook to show contact photos
      $this->add_hook('contact_photo', array($this, 'contact_photo'));
    }

    else if ($this->rcmail->task == 'settings') {
      $dont_override = $this->rcmail->config->get('dont_override', array());
      if (!in_array('show_gravatar', $dont_override)) {
        $this->add_hook('preferences_list', array($this, 'prefs_table'));
        $this->add_hook('preferences_save', array($this, 'save_prefs'));
      }
    }

  }

  // returns true if rc is running on https protocol
  function is_https()
  {
    return ( $_SERVER["HTTPS"] == 'on' || $_SERVER["HTTP_FRONT_END_HTTPS"] == 'on' 
              || preg_match("/^https:/", $_SERVER['SCRIPT_URI']) ) ? true : false;
  }

  function enabled($option)
  {
    return $this->rcmail->config->get($option) ? true : false;
  }

  // helper for checkbox
  function checkbox($option, &$options)
  {
      $value = $this->rcmail->config->get($option);
      $checkbox = new html_checkbox(array('name' => '_'.$option, 'id' => $option, 'value' => 1));

      $options[$option] = array(
        'title' => html::label($option, Q($this->gettext($option))),
        'content' => $checkbox->show($value?1:0)
      );
  }

  // check if array is associative
  function is_assoc($array) {
        return (is_array($array) && (0 !== count(array_diff_key($array, array_keys(array_keys($array)))) || count($array)==0));
  }

  // helper for select
  function select($option, $possible_options, $default, &$options)
  {
      $value = $this->rcmail->config->get($option, $default);
      $select = new html_select(array('name' => '_'.$option));

      // if associative, build needed arrays
      if ( $this->is_assoc($possible_options) ) {

        $opt_labels = array();
        $opt_attrs = array();

        foreach($possible_options as $attr => $label) {
          $opt_labels[] = $label;
          $opt_attrs[] = (string)$attr;
        }

        $select->add($opt_labels, $opt_attrs);

      // else options and attrs are same
      } else {
        $select->add($possible_options, $possible_options);
      }


      $options[$option] = array(
        'title' => html::label($option, Q($this->gettext($option))),
        'content' => $select->show($value)
      );
  }

  function prefs_table($args)
  {
    $options = array();

    if (!$this->nativepics) {
      $this->checkbox('gravatar_enable_preview', $options);
      $this->checkbox('gravatar_enable_message', $options);
    }

    $this->select('gravatar_size', array('16','24','32','48','64','128'), "{$this->default_size}", $options);
    $this->select('gravatar_rating', 
                    array('g' => Q($this->gettext('gravatar_G')),
                          'pg' => Q($this->gettext('gravatar_PG')),
                          'r' => Q($this->gettext('gravatar_R')),
                          'x' => Q($this->gettext('gravatar_X'))
                      ), $this->default_rating, $options);

    if (!$this->nativepics) {
      $this->select('gravatar_default', 
                    array('' => 'Blue G',
                          'identicon' => 'Identicon',
                          'monsterid' => 'Monsterid',
                          'wavatar' => 'Wavatar',
                          'mm' => 'Mistery-man',
                          '404' => Q($this->gettext('gravatar_none'))
                      ), $this->default_default, $options);

      $this->checkbox('gravatar_border', $options);
    }

    if ($args['section'] == 'mailview') {

      $args['blocks']['gravatar'] = array(
        'name' => Q($this->gettext('gravatars')),
        'options' => $options
      );
    }

    return $args;
  }

  function save_prefs($args)
  {
    if ($args['section'] == 'mailview') {
      if (!$this->nativepics) {
        $args['prefs']['gravatar_enable_preview'] = get_input_value('_gravatar_enable_preview', RCUBE_INPUT_POST);
        $args['prefs']['gravatar_enable_message'] = get_input_value('_gravatar_enable_message', RCUBE_INPUT_POST);
      }

      $args['prefs']['gravatar_size'] = get_input_value('_gravatar_size', RCUBE_INPUT_POST);
      $args['prefs']['gravatar_rating'] = get_input_value('_gravatar_rating', RCUBE_INPUT_POST);

      if (!$this->nativepics) {
        $args['prefs']['gravatar_default'] = get_input_value('_gravatar_default', RCUBE_INPUT_POST);
        $args['prefs']['gravatar_border'] = get_input_value('_gravatar_border', RCUBE_INPUT_POST);
      }
      return $args;
    }
  }

  function contact_photo($p)
  {
    // if no contact photo was found
    if (!$p['data']) {
      // TODO: try for every email address of contact record?
      $emails = rcube_addressbook::get_col_values('email', $p['record'], true);
      $email = $p['email'] ? $p['email'] : $emails[0];

      if ($email) {
        $this->gravatar_id = md5(strtolower($email));
        $url = $this->gravatar_url();

        $headers = get_headers($url);
        if (is_array($headers) && preg_match("/200 OK/", $headers[0]))
          $p['url'] = $url;
      }
    }

    return $p;
  }

  function message_load($p)
  {
    $this->sender = (array)$p['object']->sender;
    $this->gravatar_id = md5(strtolower($this->sender['mailto']));
  }

  function gravatar()
  {
    $url = $this->gravatar_url();

    // check if remote image doesn't return 404 if we use
    // 404 for default gravatar
    if ( $this->default == '404' ) {
       $headers = get_headers($url);

       if ( !is_array($headers) || preg_match("/404 Not Found/", $headers[0]) ) 
          return;
    }

    return html::div(array('class' => 'gravatar'.($this->border?' gravatarBorder':'') ),
      html::img(array('src' => $url, 'title' => 'Gravatar')));
  }

  function gravatar_url()
  {
    return $this->gravatar_url . "avatar/" . $this->gravatar_id
      . "?s=" . $this->size
      . "&r=" . $this->rating
      . "&d=" . $this->default;
  }

  function html_output($p)
  {
    $p['content'] = $this->gravatar().$p['content'];

    return $p;
  } 

  function render_page($p)
  {
    $this->rcmail->output->add_header(
      html::tag('link', array('rel' => 'dns-prefetch',
                'href' => $this->gravatar_url)));
  }
}
