<?php

class TimberComment extends TimberCore {

    var $PostClass = 'TimberPost';

    public static $representation = 'comment';

    function __construct($cid) {
        $this->init($cid);
    }

    function init($cid) {
        $comment_data = $cid;
        if (is_integer($cid)) {
            $comment_data = get_comment($cid);
        }
        $this->import($comment_data);
        $this->ID = $this->comment_ID;
        $comment_meta_data = $this->get_meta_fields($this->ID);
        $this->import($comment_meta_data);
    }

    public function author() {
        if ($this->user_id) {
            return new TimberUser($this->user_id);
        } else {
            $author = new TimberUser(0);
            if (isset($this->comment_author) && $this->comment_author){
                $author->name = $this->comment_author;
            } else {
                $author->name = 'Anonymous';
            }
        }
        return $author;
    }

    public function avatar($size=92, $default=''){
        // Fetches the Gravatar
        // use it like this
        // {{comment.avatar(36,template_uri~"/img/dude.jpg")}}

        if ( ! get_option('show_avatars') ) return false;
        if ( !is_numeric($size) ) $size = '92';

        $email = $this->avatar_email();
        $email_hash = '';
        if ( !empty($email) ){
            $email_hash = md5( strtolower( trim( $email ) ) );
        }
        $host = $this->avatar_host($email_hash);
        $default = $this->avatar_default($default, $email, $size, $host);
        if( !empty($email) ) {
            $avatar = $this->avatar_out($email, $default, $host, $email_hash, $size);
        } else {
            $avatar = $default;
        }
        return $avatar;
    }

    public function content() {
        return $this->comment_content;
    }

    public function date() {
        return $this->comment_date;
    }

    public function meta($field_name){
        return $this->get_meta_field($field_name);
    }

    private function get_meta_fields($comment_id = null){
        if ($comment_id === null){
            $comment_id = $this->ID;
        }
        //Could not find a WP function to fetch all comment meta data, so I made one.
        global $wpdb;
        $query = $wpdb->prepare("SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $comment_id);
        $metas = $wpdb->get_results($query);
        $customs = array();
        foreach($metas as $meta_row){
            $customs[$meta_row->meta_key] = maybe_unserialize($meta_row->meta_value);
        }
        return $customs;
    }

    private function get_meta_field($field_name){

    }

    /* AVATAR Stuff
    ======================= */

    private function avatar_email(){
		$email = '';
		$id = (int) $this->user_id;
		$user = get_userdata($id);
		if ($user){
			$email = $user->user_email;
		} else {
			$email = $this->comment_author_email;
		}
		return $email;
    }

    private function avatar_host($email_hash){
        if ( is_ssl() ) {
            $host = 'https://secure.gravatar.com';
        } else {
            if ( !empty($email_hash) ){
                $host = sprintf( "http://%d.gravatar.com", ( hexdec( $email_hash[0] ) % 2 ) );
            } else {
                $host = 'http://0.gravatar.com';
            }
        }
        return $host;
    }

    private function avatar_default($default, $email, $size, $host){
		# what if its relative.
		if(substr ( $default , 0, 1 ) == "/" ){
			$default = home_url() . $default;
		}

		if (empty($default) ){
			$avatar_default = get_option('avatar_default');
       		if ( empty($avatar_default) ){
            	$default = 'mystery';
        	} else {
           		$default = $avatar_default;
        	}
		}
      	if ( 'mystery' == $default ) {
			$default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536?s={$size}";
			// ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		} elseif ( 'blank' == $default ) {
          	$default = $email ? 'blank' : includes_url( 'images/blank.gif' );
      	} elseif ( !empty($email) && 'gravatar_default' == $default ) {
          	$default = '';
      	} elseif ( 'gravatar_default' == $default ) {
          	$default = "$host/avatar/?s={$size}";
      	} elseif ( empty($email) && !strstr($default, 'http://') ) {
          	$default = "$host/avatar/?d=$default&amp;s={$size}";
      	} elseif ( strpos($default, 'http://') === 0 ) {
            //theyre just linking to an image so don't do anything else
          	//$default = add_query_arg( 's', $size, $default );
        }
		return $default;
    }

    private function avatar_out($email, $default, $host, $email_hash, $size){
        $out = "$host/avatar/";
        $out .= $email_hash;
        $out .= '?s='.$size;
        $out .= '&amp;d=' . urlencode( $default );
        $rating = get_option('avatar_rating');
        if ( !empty( $rating ) ) $out .= "&amp;r={$rating}";
        return str_replace( '&#038;', '&amp;', esc_url( $out ) );
    }
}
