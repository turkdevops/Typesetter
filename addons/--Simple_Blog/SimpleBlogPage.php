<?php
defined('is_running') or die('Not an entry point...');



class SimpleBlogPage{

	var $post_id;
	var $post;
	var $comment_saved		= false;
	var $comments_closed;

	function __construct($post_id){
		$this->post_id			= $post_id;
		$this->post				= SimpleBlogCommon::GetPostContent($this->post_id);
		$this->comments_closed	= SimpleBlogCommon::AStrValue('comments_closed',$this->post_id);
	}


	/**
	 * Display the blog post
	 *
	 */
	function ShowPost(){
		global $page;

		if( $this->post === false ){
			$this->Error_404();
			return;
		}

		if( !common::LoggedIn() && SimpleBlogCommon::AStrValue('drafts',$this->post_id) ){
			$this->Error_404();
			return;
		}

		$this->PostCommands();

		$page->label	= SimpleBlogCommon::Underscores( $this->post['title'] );


		echo '<div class="blog_post single_blog_item">';

		//heading
		$header			= '<h2 id="blog_post_'.$this->post_id.'">';
		if( SimpleBlogCommon::AStrValue('drafts',$this->post_id) ){
			$header		.= '<span style="opacity:0.3;">';
			$header		.= gpOutput::SelectText('Draft');
			$header		.= '</span> ';
		}

		$header			.= SimpleBlogCommon::PostLink($this->post_id,$page->label);
		$header			.= '</h2>';
		SimpleBlogCommon::BlogHead($header,$this->post_id,$this->post);


		//content
		echo '<div class="twysiwygr">';
		echo $this->post['content'];
		echo '</div>';

		echo '</div>';

		echo '<br/>';

		echo '<div class="clear"></div>';

		$this->Categories();
		$this->PostLinks();
		$this->Comments();
	}


	/**
	 * Run commands
	 *
	 */
	function PostCommands(){
		global $page;

		$cmd = common::GetCommand();

		if( empty($cmd) ){
			//redirect to correct url if needed
			SimpleBlogCommon::UrlQuery( $this->post_id, $expected_url, $query );
			$expected_url = str_replace('&amp;','&',$expected_url); //because of htmlspecialchars($cattitle)
			if( $page->requested != $expected_url ){
				$expected_url = common::GetUrl( $expected_url, $query, false );
				common::Redirect($expected_url);
			}
			return;
		}


		switch($cmd){
			case 'Add Comment':
				$this->AddComment();
			break;
		}

	}


	/**
	 * Ouptut blog categories
	 *
	 */
	function Categories(){

		//blog categories
		if( empty($this->post['categories']) ){
			return;
		}

		$temp = array();
		foreach($this->post['categories'] as $catindex){
			$title = SimpleBlogCommon::AStrValue( 'categories', $catindex );
			if( !$title ){
				continue;
			}
			if( SimpleBlogCommon::AStrValue('categories_hidden',$catindex) ){
				continue;
			}
			$temp[] = SimpleBlogCommon::CategoryLink($catindex, $title, $title);
		}

		if( count($temp) ){
			echo '<div class="category_container">';
			echo '<b>';
			echo gpOutput::GetAddonText('Categories');
			echo ':</b> ';
			echo implode(', ',$temp);
			echo '</div>';
		}
	}


	/**
	 * Output blog comments
	 *
	 */
	function Comments(){

		//comments
		if( !SimpleBlogCommon::$data['allow_comments'] ){
			return;
		}

		echo '<div class="comment_container">';
		$this->ShowComments();
		$this->CommentForm();
		echo '</div>';

	}


	/**
	 * Show the comments for a single blog post
	 *
	 */
	function ShowComments(){

		$data = $this->GetCommentData();
		if( empty($data) ){
			return;
		}

		echo '<h3>';
		echo gpOutput::GetAddonText('Comments');
		echo '</h3>';

		$this->GetCommentHtml($data,true);

	}


	/**
	 * Get the comment data for a single post
	 *
	 */
	function GetCommentData(){

		// pre 1.7.4
		$file	= SimpleBlogCommon::$data_dir.'/comments_data_'.$this->post_id.'.txt';
		$data	= SimpleBlogCommon::FileData($file);
		if( $data ){
			return $data;
		}

		// 1.7.4+
		$file	= SimpleBlogCommon::$data_dir.'/comments/'.$this->post_id.'.txt';
		$data	= SimpleBlogCommon::FileData($file);
		if( $data ){
			return $data;
		}


		return array();
	}



	/**
	 * Add a comment to the comment data for a post
	 *
	 */
	function AddComment(){
		global $langmessage;

		if( $this->comments_closed ){
			return;
		}

		$data = $this->GetCommentData();

		//need a captcha?
		if( SimpleBlogCommon::$data['comment_captcha'] && gp_recaptcha::isActive() ){

			if( !isset($_POST['anti_spam_submitted']) ){
				return false;

			}elseif( !gp_recaptcha::Check() ){
				return false;

			}
		}

		if( empty($_POST['name']) ){
			$field = gpOutput::SelectText('Name');
			message($langmessage['OOPS_REQUIRED'],$field);
			return false;
		}

		if( empty($_POST['comment']) ){
			$field = gpOutput::SelectText('Comment');
			message($langmessage['OOPS_REQUIRED'],$field);
			return false;
		}


		$temp = array();
		$temp['name'] = htmlspecialchars($_POST['name']);
		$temp['comment'] = nl2br(strip_tags($_POST['comment']));
		$temp['time'] = time();

		if( !empty($_POST['website']) && ($_POST['website'] !== 'http://') ){
			$website = $_POST['website'];
			if( SimpleBlogCommon::strpos($website,'://') === false ){
				$website = false;
			}
			if( $website ){
				$temp['website'] = $website;
			}
		}

		$data[] = $temp;

		if( !$this->SaveCommentData($data) ){
			message($langmessage['OOPS']);
			return false;
		}

		message($langmessage['SAVED']);


		//email new comments
		if( !empty(SimpleBlogCommon::$data['email_comments']) ){



			$subject = 'New Comment';
			$body = '';
			if( !empty($temp['name']) ){
				$body .= '<p>From: '.$temp['name'].'</p>';
			}
			if( !empty($temp['website']) ){
				$body .= '<p>Website: '.$temp['name'].'</p>';
			}
			$body .= '<p>'.$temp['comment'].'</p>';

			global $gp_mailer;
			includeFile('tool/email_mailer.php');
			$gp_mailer->SendEmail(SimpleBlogCommon::$data['email_comments'], $subject, $body);
		}


		$this->comment_saved = true;
		return true;
	}


	/**
	 * Output the html for a blog post's comments
	 *
	 */
	function GetCommentHtml( $data ){
		global $langmessage;

		if( !is_array($data) ){
			return;
		}

		foreach($data as $key => $comment){
			echo '<div class="comment_area">';
			echo '<p class="name">';
			if( (SimpleBlogCommon::$data['commenter_website'] == 'nofollow') && !empty($comment['website']) ){
				echo '<b><a href="'.$comment['website'].'" rel="nofollow">'.$comment['name'].'</a></b>';
			}elseif( (SimpleBlogCommon::$data['commenter_website'] == 'link') && !empty($comment['website']) ){
				echo '<b><a href="'.$comment['website'].'">'.$comment['name'].'</a></b>';
			}else{
				echo '<b>'.$comment['name'].'</b>';
			}
			echo ' &nbsp; ';
			echo '<span>';
			echo strftime(SimpleBlogCommon::$data['strftime_format'],$comment['time']);
			echo '</span>';


			if( common::LoggedIn() ){
				echo ' &nbsp; ';
				$attr = 'class="delete gpconfirm" title="'.$langmessage['delete_confirm'].'" name="postlink" data-nonce= "'.common::new_nonce('post',true).'"';
				echo SimpleBlogCommon::PostLink($this->post_id,$langmessage['delete'],'cmd=delete_comment&comment_index='.$key,$attr);
			}


			echo '</p>';
			echo '<p class="comment">';
			echo $comment['comment'];
			echo '</p>';
			echo '</div>';
		}
	}


	/**
	 * Display the visitor form for adding comments
	 *
	 */
	function CommentForm(){

		if( $this->comments_closed ){
			echo '<div class="comments_closed">';
			echo gpOutput::GetAddonText('Comments have been closed.');
			echo '</div>';
			return;
		}


		$_POST += array('name'=>'','website'=>'http://','comment'=>'');

		echo '<h3>';
		echo gpOutput::GetAddonText('Leave Comment');
		echo '</h3>';


		echo '<form method="post" action="'.SimpleBlogCommon::PostUrl($this->post_id).'">';
		echo '<ul>';

		//name
		echo '<li>';
		echo '<label>';
		echo gpOutput::GetAddonText('Name');
		echo '</label><br/>';
		echo '<input type="text" name="name" class="text" value="'.htmlspecialchars($_POST['name']).'" />';
		echo '</li>';

		//website
		if( !empty(SimpleBlogCommon::$data['commenter_website']) ){
			echo '<li>';
			echo '<label>';
			echo gpOutput::GetAddonText('Website');
			echo '</label><br/>';
			echo '<input type="text" name="website" class="text" value="'.htmlspecialchars($_POST['website']).'" />';
			echo '</li>';
		}


		//comment
		echo '<li>';
		echo '<label>';
		echo gpOutput::ReturnText('Comment');
		echo '</label><br/>';
		echo '<textarea name="comment" cols="30" rows="7" >';
		echo htmlspecialchars($_POST['comment']);
		echo '</textarea>';
		echo '</li>';


		//recaptcha
		if( SimpleBlogCommon::$data['comment_captcha'] && gp_recaptcha::isActive() ){
			echo '<input type="hidden" name="anti_spam_submitted" value="anti_spam_submitted" />';
			echo '<li>';
			echo '<label>';
			echo gpOutput::ReturnText('captcha');
			echo '</label><br/>';
			gp_recaptcha::Form();
			echo '</li>';
		}

		//submit button
		echo '<li>';
		echo '<input type="hidden" name="cmd" value="Add Comment" />';
		$html = '<input type="submit" name="" class="submit" value="%s" />';
		echo gpOutput::GetAddonText('Add Comment',$html);
		echo '</li>';

		echo '</ul>';
		echo '</form>';

	}


	/**
	 * Save the comment data for a blog post
	 *
	 */
	function SaveCommentData($data){
		global $langmessage;


		// check directory
		$dir = SimpleBlogCommon::$data_dir.'/comments';
		if( !gpFiles::CheckDir($dir) ){
			return false;
		}

		$commentDataFile = $dir.'/'.$this->post_id.'.txt';
		$dataTxt = serialize($data);
		if( !gpFiles::Save($commentDataFile,$dataTxt) ){
			return false;
		}


		// clean pre 1.7.4 files
		$commentDataFile = SimpleBlogCommon::$data_dir.'/comments_data_'.$this->post_id.'.txt';
		if( file_exists($commentDataFile) ){
			unlink($commentDataFile);
		}

		SimpleBlogCommon::AStrValue('comment_counts',$this->post_id,count($data));

		SimpleBlogCommon::SaveIndex();

		//clear comments cache
		$cache_file = SimpleBlogCommon::$data_dir.'/comments/cache.txt';
		if( file_exists($cache_file) ){
			unlink($cache_file);
		}

		return true;
	}

	/**
	 * todo: better 404 page
	 *
	 */
	function Error_404(){
		global $langmessage;

		message($langmessage['OOPS']);
	}


	/**
	 * Display the links at the bottom of a post
	 *
	 */
	function PostLinks(){

		$post_key = SimpleBlogCommon::AStrKey('str_index',$this->post_id);

		echo '<p class="blog_nav_links">';


		//blog home
		$html = common::Link('Special_Blog','%s','','class="blog_home"');
		echo gpOutput::GetAddonText('Blog Home',$html);
		echo '&nbsp;';


		// check for newer posts and if post is draft
		$isDraft = false;
		if( $post_key > 0 ){

			$i = 0;
			do {
				$i++;
				$next_index = SimpleBlogCommon::AStrValue('str_index',$post_key-$i);
				if( !common::loggedIn() ){
					$isDraft = SimpleBlogCommon::AStrValue('drafts',$next_index);
				}
			}while( $isDraft );

			if( !$isDraft ){
				$html = SimpleBlogCommon::PostLink($next_index,'%s','','class="blog_newer"');
				echo gpOutput::GetAddonText('Newer Entry',$html);
				echo '&nbsp;';
			}
		}


		//check for older posts and if older post is draft
		$i = 0;
		$isDraft = false;
		do{
			$i++;
			$prev_index = SimpleBlogCommon::AStrValue('str_index',$post_key+$i);

			if( $prev_index === false ){
				break;
			}

			if( !common::loggedIn() ){
				$isDraft = SimpleBlogCommon::AStrValue('drafts',$prev_index);
			}

			if( !$isDraft ){
				$html = SimpleBlogCommon::PostLink($prev_index,'%s','','class="blog_older"');
				echo gpOutput::GetAddonText('Older Entry',$html);
			}

		}while( $isDraft );


		if( common::LoggedIn() ){
			echo '&nbsp;';
			echo common::Link('Special_Blog','New Post','cmd=new_form','class="blog_post_new"');
		}

		echo '</p>';
	}

}

