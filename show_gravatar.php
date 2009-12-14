<?php

/**
 * Show gravatar for sender
 *
 * This plugin will show gravatar picture for message sender.
 *
 * Enable the plugin in config/main.inc.php
 *
 * @version 0.1
 * @author Ondra 'Kepi' Kudlik
 * @website http://kepi.cz/
 */

class show_gravatar extends rcube_plugin
{
  public $task = 'mail';

  private $gravatar_id;
  private $sender;

  function init()
  {
    $rcmail = rcmail::get_instance();
    if ($rcmail->action == 'show' || $rcmail->action == 'preview') {
      $this->add_hook('message_load', array($this, 'message_load'));
      $this->add_hook('template_object_messageheaders', array($this, 'html_output'));

      $skin = $rcmail->config->get('skin');
      if (!file_exists($this->home."/skins/$skin/help.css"))
        $skin = 'default';
  
      // add style for placing gravatar icon
      $this->include_stylesheet("skins/$skin/show_gravatar.css");
    }
  }

  function message_load($p)
  {
    $this->sender = (array)$p['object']->sender;
    $this->gravatar_id = md5(strtolower($this->sender['mailto']));
  }

  function gravatar()
  {
    $size = 64;

    $url = "http://www.gravatar.com/avatar.php?gravatar_id=" . $this->gravatar_id . "&size=" . $size;
    return html::div(array('class' => 'gravatar'), html::img(array('src' => $url, 'alt' => 'Gravatar')));
  }

  function html_output($p)
  {
    $p['content'] = $this->gravatar().$p['content'];

    return $p;
  } 
}
