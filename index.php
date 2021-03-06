<?php
/*
Plugin Name: Never Loose Contact Form
Plugin URI: 
Description: Simple to use spam free contact form using simple checkbox captcha, saving messages to database and emailing your admin contact
Author: Andy Moyle
Version: 0.41
Author URI: http://www.themoyles.co.uk/web-development/contact-form-plugin/
*/
if (!function_exists ('add_action')):
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
endif;
$never_loose_contact_settings=maybe_unserialize(get_option('never_loose_contact_form_settings'));
define('CONT_TBL',$table_prefix.'contact_form');
define('CONT_URL',WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)));
add_action('init','contact_form_install');
function contact_form_install()
{
    global $wpdb, $never_loose_contact_settings;
    $never_loose_contact_settings=get_option('never_loose_contact_form_settings');
    if(empty($never_loose_contact_settings))
    {
        //copy old settings to never_loose_contact_settings and get rid of old settings 
        $old_settings=get_option('contact_form_settings');
        if($old_settings)
        {
            $never_loose_contact_settings=$old_settings;
            delete_option('contact_form_settings');
        }
        $never_loose_contact_settings['version']=0.40;//current version number
        update_option('never_loose_contact_form_settings',$never_loose_contact_settings);
        $wpdb->query('CREATE TABLE IF NOT EXISTS '.CONT_TBL.' (`name` text NOT NULL,`comment` text NOT NULL,`subject` text NOT NULL, `email` text NOT NULL,`post_date` datetime NOT NULL,`read` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",`id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;');
        if($wpdb->get_var('SHOW COLUMNS FROM '.CONT_TBL.' LIKE "ip"')!='ip')
        {
            $wpdb->query('ALTER TABLE '.CONT_TBL.' ADD `ip` TEXT NOT NULL');
        }
    }
}

//add localisation
$nlcf_translator_domain   = 'nlcf';
$nlcf_is_setup = 0;
function nlcf_loc_setup(){
  global $nlcf_translator_domain, $nlcf_translator_is_setup;
  if($nlcf_translator_is_setup) {
    return;
  }
  load_plugin_textdomain( 'nlcf', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
  $nlcf_translator_is_setup = 1;
}
add_action('plugins_loaded', 'nlcf_loc_setup');
// Admin Bar Customisation
function contact_form_admin_bar_render() {
 global $wp_admin_bar,$wpdb,$current_user,$never_loose_contact_settings;
 if(current_user_can('manage_options'))
 {
    $sql='SELECT Count(*) FROM '.CONT_TBL.' WHERE DATE(post_date)=CURDATE()';
    $count=$wpdb->get_var($sql);
    // Add a new top level menu link
    // Here we add a customer support URL link
    $wp_admin_bar->add_menu( array('parent' => false, 'id' => 'contact form', 'title' => __('Contact Form ','nlcf'). $count.' '.__('Today','nlcf'), 'href' => admin_url().'admin.php?page=contact_form/index.php' ));
    $wp_admin_bar->add_menu(array('parent' => 'contact_form','id' => 'contact_form_settings', 'title' => __('Settings','nlcf'), 'href' => admin_url().'admin.php?page=contact_form/index.php&action=contact_form_settings' ));
 }
}

// Finally we add our hook function
add_action( 'wp_before_admin_bar_render', 'contact_form_admin_bar_render' );
//front_end
add_action('wp_enqueue_scripts','contact_form_css');
function contact_form_css()
{
    wp_enqueue_style('contact_form_css',WP_PLUGIN_URL.'/never-loose-contact-form/contact-form.css');
    
    wp_enqueue_script('nlcf_js',WP_PLUGIN_URL.'/never-loose-contact-form/nlcf.js',array('jquery'),NULL,TRUE);
}
add_shortcode('contact_form','contact_form_shortcode');


function contact_form_shortcode($atts, $content = null)
{
     return contact_form(false);
}

function contact_form($widget=false)
{
    
    global $wpdb,$never_loose_contact_settings;
    $out='';
    $id=get_current_user_id();
    if(!empty($_POST['save_contact_form_message'])&&!empty($_POST['contact_form_comment']) &&!empty($_POST['contact_form_email'])&&!empty($_POST['contact_form_name'])&& wp_verify_nonce($_POST['contact_form_nonce'],'contact_form_comment'))
    {
        if(empty($_POST['nlcf']))
        {//not real human checked
                $out.='<div style="background-color: #eaeaea; border: 1px solid #D5D5D5; border-radius:5px;font-family: arial,helvetica,sans-serif; font-size: 13px; line-height: 18px; margin-bottom: 20px; margin-top: 8px; padding: 15px 20px 15px 20px; "><p>'.__("You appear to be a spammer, so the message wasn't sent",'nlcf').'.</p></div>';
        }
        elseif(substr_count($_POST['contact_form_comment'], "http") > $settings['url'])
        {//too many urls
            $out.='<div style="background-color: #eaeaea; border: 1px solid #D5D5D5; border-radius:5px;font-family: arial,helvetica,sans-serif; font-size: 13px; line-height: 18px; margin-bottom: 20px; margin-top: 8px; padding: 15px 20px 15px 20px; "><p>'.__("Message was not sent. There were too many web links in it - makes it look like you are a spammer.",'nlcf').'</p></div>';
        }//end too many urls
        elseif(substr_count($_POST['contact_form_subject'], "http") > 0)
        {
            $out.='<div style="background-color: #eaeaea; border: 1px solid #D5D5D5; border-radius:5px;font-family: arial,helvetica,sans-serif; font-size: 13px; line-height: 18px; margin-bottom: 20px; margin-top: 8px; padding: 15px 20px 15px 20px; "><p>'.__("Message was not sent. Web links in the subject is a pretty spammy thing to do.",'nlcf').'</p></div>';
        }
        elseif(substr_count($_POST['contact_form_name'], "http") > 0)
        {
            $out.='<div style="background-color: #eaeaea; border: 1px solid #D5D5D5; border-radius:5px;font-family: arial,helvetica,sans-serif; font-size: 13px; line-height: 18px; margin-bottom: 20px; margin-top: 8px; padding: 15px 20px 15px 20px; "><p>'.__("Message was not sent. Web links in the subject is a pretty spammy thing to do.",'nlcf').'</p></div>';
        }
        else
        {//reasonably happy it's not spam
            $form=array();
            foreach($_POST AS $key=>$value)$form[$key]=sanitize_text_field(stripslashes($value));
        
                $sql=array();
                foreach($form AS $key=>$value)$sql[$key]=esc_sql($value);
                $check=$wpdb->get_var('SELECT id FROM '.CONT_TBL.' WHERE name="'.$sql['contact_form_name'].'" AND email="'.$sql['contact_form_email'].'" AND comment="'.$sql['contact_form_comment'].'" AND subject="'.$sql['contact_form_subject'].'" AND ip="'.esc_sql($_SERVER['REMOTE_ADDR']).'" ');
                if(!$check)
                {
                    $wpdb->query('INSERT INTO '.CONT_TBL.' (name,email,subject,comment,post_date,ip)VALUES("'.$sql['contact_form_name'].'","'.$sql['contact_form_email'].'","'.$sql['contact_form_subject'].'","'.$sql['contact_form_comment'].'","'.date('Y-m-d H:i:s').'","'.esc_sql($_SERVER['REMOTE_ADDR']).'")');
                    $out='<div style="background-color: #eaeaea; border: 1px solid #D5D5D5; border-radius:5px;font-family: arial,helvetica,sans-serif; font-size: 13px; line-height: 18px; margin-bottom: 20px; margin-top: 8px; padding: 15px 20px 15px 20px; "><p>'.__('Your message has been sent','nlcf').'</p></div>';
                    $to=get_option('admin_email');
                    $subject='Website Message';
					$headers='From: '.esc_html($form['contact_form_name']).' <'.esc_html($form['contact_form_email']).'>';
                    $message='<table><tr><td>'.__('Name','nlcf').':</td><td>'.esc_html($form['contact_form_name']).'</td></tr>';
                    $message.='<tr><td>'.__('Email','nlcf').':</td><td>'.esc_html($form['contact_form_email']).'</td></tr>';
                    $message.='<tr><td>'.__('IP Address','nlcf').':</td><td>'.esc_html($_SERVER['REMOTE_ADDR']).'</td></tr>';
                    $message.='<tr><td>'.__('Message','nlcf').':</td><td>'.esc_html($form['contact_form_comment']).'</td></tr></table>';
                    add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
                    wp_mail($to,$subject,$message,$headers); 
					remove_filter( 'wp_mail_content_type', 'set_html_content_type' );
                }//not already in db
        }//not spam
       
        
    }//process form
    else
    {//form
        $out='';
        if(!$widget){$out.='<div class="contact_form_wrap">';}else{$out.='<div class="contact_form_widget">';}
        $never_loose_contact_settings=maybe_unserialize(get_option('never_loose_contact_form_settings'));
        if(!empty($never_loose_contact_settings['address']))$out.='<p><img src="'.CONT_URL.'Write.png" class="middle" width="24" height="24" alt="'.__('Write to','nlcf').'..."/>'.esc_html($never_loose_contact_settings['address']).' </p>';
        if(!empty($never_loose_contact_settings['phone']))$out.='<p><img src="'.CONT_URL.'Phone.png" width="24" class="middle" height="24" alt="'.__('Phone us','nlcf').'..."/> '.esc_html($never_loose_contact_settings['phone']).' </p>';
        if(!empty($never_loose_contact_settings['email']))$out.='<p><img src="'.CONT_URL.'Email.png" width="24" class="middle" height="24" alt="'.__('Email us','nlcf').'..."/>'.esc_html($never_loose_contact_settings['email']).'</p>';
                                
        $out.='<form  action="'.get_permalink().'" method="post" >';
        $out.='<p><label for="contact_name">Name</label><input id="contact_name" class="text_input" type="text" name="contact_form_name"';
        if($id)$info=get_userdata($id);
        if($info)$out.=' value="'.$info->user_nicename.'" ';
        $out.='/></p>';
        $out.='<p><label for="contact_email">'.__('Email','nlcf').'</label><input type="text" id="contact_email" class="text_input" name="contact_form_email"';
        if($info)$out.=' value="'.$info->user_email.'" ';
        $out.='/></p>';
        $out.='<p><label for="contact_subject">'.__('Subject','nlcf').'</label><input type="text" id="contact_subject" class="text_input" name="contact_form_subject"';
        
        $out.='/></p>';
        $out.='<p><label>'.__('Message','nlcf').'</label><textarea  class="textarea" name="contact_form_comment"></textarea></p>';
        $out.=str_replace('id="contact_form_nonce"','',wp_nonce_field('contact_form_comment','contact_form_nonce',false));
        $out.='<div class="never-loose-contact-form">'.__('Please enable javascript to leave message','nlcf').'</div>';
        $out.='<p><input type="hidden" name="save_contact_form_message" value="yes"/><input type="submit"  value="'.__('Send Message','nlcf').'" class="button"/></p></form>';
        $out.='</div>';
    }//end form
    
    return $out;
}




//end front end

//back end
//Admin Menu
add_action('admin_menu', 'contact_form_admin_menus');
function contact_form_admin_menus() 

{
    //add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
    add_menu_page('Contact Form', 'Contact Form',  'administrator', 'contact_form/index.php', 'contact_form_main');
    add_submenu_page('contact_form/index.php', 'Settings', 'Settings', 'administrator', 'contact_form_settings', 'contact_form_settings');    
}
//End Admin Menu
function contact_form_settings()
{
     
    echo'<h2>Settings</h2>';
    if(!empty($_POST['save_contact_form_settings']))
    {
        $form=array();
        foreach($_POST AS $key=>$value)$form[$key]=stripslashes($value);
		$new_settings=serialize(array('phone'=>$form['phone'],'address'=>$form['address'],'email'=>$form['email'],'url'=>(int)$form['url']));
		var_dump($new_settings);
		
        update_option('never_loose_contact_form_settings',$new_settings);
		
        echo'<div class="updated fade"><p><strong>Settings Updated</strong></p></div>';
        echo'<form class="right" action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="R7YWSEHFXEU52"><input type="image"  src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif"  name="submit" alt="PayPal - The safer, easier way to pay online."><img alt=""  border="0" src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1"></form>';
    
    }
    
    
        $never_loose_contact_settings=maybe_unserialize(get_option('never_loose_contact_form_settings'));
		
        echo'<p>'.__("If you would like the shortcode and/or widget to display your contact details above the email form, please fill in this form. If not leave it blank. Public contact form submission will be sent to your wordpress Admin Email contact",'nlcf').'</p><form action="" method="POST">';
        echo'<p><label style="width:100px;float:left;">'.__('Address','nlcf').'</label><input type="text" name="address" ';
        if(!empty($never_loose_contact_settings['address']))echo' value="'.esc_html($never_loose_contact_settings['address']).'" ';
        echo'/></p>';
        echo'<p><label style="width:100px;float:left;">'.__('Phone','nlcf').'</label><input type="text" name="phone" ';
        if(!empty($never_loose_contact_settings['phone']))echo' value="'.esc_html($never_loose_contact_settings['phone']).'" ';
        echo'/></p>';
        echo'<p><label style="width:100px;float:left;">'.__('Email','nlcf').'</label><input type="text" name="email" ';
        if(!empty($never_loose_contact_settings['email']))echo' value="'.esc_html($never_loose_contact_settings['email']).'" ';
        echo'/></p>';
        echo'<p><label style="width:100px;float:left;">'.__('Max URLs in message','nlcf').'</label><input type="text" name="url" ';
        if(!empty($never_loose_contact_settings['url']))echo' value="'.esc_html($never_loose_contact_settings['url']).'" ';
        echo'/></p>';
        echo'<p class="submit"><input type="hidden" name="save_contact_form_settings" value="yes" /><input type="submit" class="primary-button" value="'.__('Save Settings','nlcf').' &raquo;"/></p></form>';
}
function contact_form_main()
{
    if(!empty($_GET['action']))
    {
        switch($_GET['action'])
        {
    
            case 'delete_comment':check_admin_referer('delete_comment');contact_form_delete_comment($_GET['id']);break;
            
           
        }
    }
    else{contact_form_list();}
}

if(isset($_GET['page'])&&$_GET['page']=="contact_form/index.php")add_action('admin_init','contact_form_thickbox');
function contact_form_thickbox()
{wp_enqueue_style('thickbox');
wp_enqueue_script('jquery');
wp_enqueue_script('thickbox');
}
function contact_form_list()
{
    
    global $wpdb,$never_loose_contact_settings;
    echo'<h2>'.__('Contact Form Messages','nlcf').'</h2><p>A plugin by <a href="http://wwww.themoyles.co.uk">Andy Moyle</a>&nbsp;<form class="right" action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="R7YWSEHFXEU52"><input type="image"  src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif"  name="submit" alt="PayPal - The safer, easier way to pay online."><img alt=""  border="0" src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1"></form></p>';
    $table='<table class="widefat"><thead><tr><th>'.__('Delete','nlcf').'</th><th>'.__('Date Posted','nlcf').'</th><th>'.__('Name','nlcf').'</th><th>'.__('Email','nlcf').'</th><th>'.__('Comment','nlcf').'</th><th>'.__('Read','nlcf').'</th></tr></thead><tfoot><tr><th>'.__('Delete','nlcf').'</th><th>'.__('Date Posted','nlcf').'</th><th>'.__('Name','nlcf').'</th><th>'.__('Email','nlcf').'</th><th>'.__('Comment','nlcf').'</th><th>'.__('Read','nlcf').'</th></tr></tfoot></tbody>';
    $results=$wpdb->get_results('SELECT * FROM '.CONT_TBL.'  ORDER BY post_date DESC');
    
    if($results)
    {
        foreach($results AS $row)
        {
            if($row->read=='0000-00-00 00:00:00'){$class=' class="contact_read" ';}else{$class='';}
            $delete='<a href="'.wp_nonce_url('admin.php?page=contact_form/index.php&amp;action=delete_comment&amp;id='.$row->id,'delete_comment').'">Delete</a>';
            $read='<input alt="#TB_inline?height=300&width=600&inlineId=message'.$row->id.'" title="Reply" class="thickbox" value="'.__('View complete message','nlcf').'" type="button" /> <div id="message'.$row->id.'" style="display:none" ><h2>'.__('Message from','nlcf').' '.$row->name.'</h2><p>'.__('Posted','nlcf').':'.mysql2date('d M Y H:i',$row->post_date).'</p><p>'.__('From','nlcf').':<a href="mailto:'.$row->email.'">'.$row->email.'</a></p><p>'.__('Subject','nlcf').': '.$row->subject.'</p><p>'.$row->comment.'</p></div>';
        
            $table.='<tr '.$class.'><td>'.$delete.'</td><td>'.mysql2date('d M Y H:i',$row->post_date).'</td><td>'.$row->name.'</td><td><a href="mailto:'.$row->email.'">'.$row->email.'<a></td><td>'.contact_form_truncate($row->comment,75,'... ').'</td><td>'.$read.'</td></tr>';
        }
        $table.='</tbody></table>';
        echo $table;
    }
    else{echo'<p>No messages yet</p>';}
    
}
function contact_form_truncate($str, $length=10, $trailing='...')
    {
    /*
    ** $str -String to truncate
    ** $length - length to truncate
    ** $trailing - the trailing character, default: "..."
    */
          // take off chars for the trailing
          $length-=mb_strlen($trailing);
          if (mb_strlen($str)> $length)
          {
             // string exceeded length, truncate and add trailing dots
             return mb_substr($str,0,$length).$trailing;
          }
          else
          {
             // string was already short enough, return the string
             $res = $str;
          }
     
          return $res;
     
    }



function contact_form_delete_comment($id)
{
    global$wpdb;
    $wpdb->query('DELETE FROM '.CONT_TBL.' WHERE id="'.esc_sql($id).'"');
    echo'<div class="updated fade"><p><strong>Comment deleted</strong></p></div>';
    echo contact_form_list();
}
//end back end

//widget
function contact_form_widget($args)
{
    global $wpdb;
    $wpdb->show_errors();
    extract($args);
    
    $title='Get in Touch';
   
    echo $before_widget;
    if ( $title )echo $before_title . $title . $after_title;
   
    echo contact_form($widget=true);
    echo $after_widget;
}
function contact_form_widget_init()
{
    wp_register_sidebar_widget('ContactForm','Contact Form','contact_form_widget');
    
    
}
add_action('init','contact_form_widget_init');
//end widget
