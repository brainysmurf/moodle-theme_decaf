<?php

include_once 'calendar/lib.php';

class theme_decaf_core_renderer extends core_renderer {

    protected $really_editing = false;

    public function navbuttons() {
        global $CFG;
	$items = $this->page->navbar->get_items();

	$content = '';
	for ($i=count($items); $i >= 0; $i--) {
	    $item = $items[$i];
	    if (!$item->parent) {
	        continue;
	    }

	    $content .= html_writer::start_tag('li');
	    $content .= html_writer::tag('a', $item->title, array('href'=>$item->action));
	    $content .= html_writer::end_tag('li');
	}

	$content .= html_writer::start_tag('li');
	$icon = html_writer::tag('i', '', array('class'=>'icon-home pull-left'));
	$content .= html_writer::tag('a', $icon.'Home', array('href'=>$CFG->wwwroot));
	$content .= html_writer::end_tag('li');

	return $content;
    }

    /**
     * Return the navbar content so that it can be echoed out by the layout
     * SSIS modifies this to only include coures name followed by whatever activity after that
     * Don't
     * @return string XHTML navbar
     */
    public function navbar() {
        global $CFG;
        $items = $this->page->navbar->get_items();
        $htmlblocks = array();
        // Iterate the navarray and display each node
        $itemcount = count($items);
        $separator = get_separator();
	$already = false;
	$htmlblocks[] = html_writer::tag('a', 'Home', array('href'=>$CFG->wwwroot));
        for ($i=0;$i < $itemcount;$i++) {
            $item = $items[$i];
	    if (!$item->parent) { 
	        // we can skip this for ssis
	        continue; 
	    }
            $item->hideicon = true;
            if ($already === false) {
                $content = html_writer::start_tag('li');
		$content .= html_writer::tag('a', $item->title, array('href'=>$item->action));
		$content .= html_writer::end_tag('li');
		$already = true;
            } else {
                $content = html_writer::tag('li', $separator.$this->render($item));
            }
            $htmlblocks[] = $content;
        }

        //accessibility: heading for navbar list  (MDL-20446)
        $navbarcontent = html_writer::tag('span', get_string('pagepath'), array('class'=>'accesshide'));
        $navbarcontent .= html_writer::tag('ul', join('', $htmlblocks), array('role'=>'navigation'));
        // XHTML
        return $navbarcontent;
    }

    /* 
    For this theme we put links into the tab as a drop down, so let's get rid of those
    */
    public function login_info($withlinks = null) {
        global $USER, $CFG, $DB, $SESSION;

        if (during_initial_install()) {
            return '';
        }

        if (is_null($withlinks)) {
            $withlinks = empty($this->page->layout_options['nologinlinks']);
        }

        $loginpage = ((string)$this->page->url === get_login_url());
        $course = $this->page->course;
        if (session_is_loggedinas()) {
            $realuser = session_get_realuser();
            $fullname = fullname($realuser, true);
            if ($withlinks) {
                $loginastitle = get_string('loginas');
                $realuserinfo = " [<a href=\"$CFG->wwwroot/course/loginas.php?id=$course->id&amp;sesskey=".sesskey()."\"";
                $realuserinfo .= "title =\"".$loginastitle."\">$fullname</a>] ";
            } else {
                $realuserinfo = " [$fullname] ";
            }
        } else {
            $realuserinfo = '';
        }

        $loginurl = get_login_url();

        if (empty($course->id)) {
            // $course->id is not defined during installation
            return '';
        } else if (isloggedin()) {
            $context = context_course::instance($course->id);

            $fullname = fullname($USER, true);
            // Since Moodle 2.0 this link always goes to the public profile page (not the course profile page)
            if ($withlinks) {
	      $linktitle = get_string('viewprofile');
	      $username = "<a href=\"$CFG->wwwroot/user/profile.php?id=$USER->id\" title=\"$linktitle\">$fullname</a>";
            } else {
                $username = $fullname;
            }
            if (is_mnet_remote_user($USER) and $idprovider = $DB->get_record('mnet_host', array('id'=>$USER->mnethostid))) {
                if ($withlinks) {
                    $username .= " from <a href=\"{$idprovider->wwwroot}\">{$idprovider->name}</a>";
                } else {
                    $username .= " from {$idprovider->name}";
                }
            }
            if (isguestuser()) {
                $loggedinas = $realuserinfo.get_string('loggedinasguest');
                if (!$loginpage && $withlinks) {
                    $loggedinas .= " (<a href=\"$loginurl\">".get_string('login').'</a>)';
                }
            } else if (is_role_switched($course->id)) { // Has switched roles
                $rolename = '';
                if ($role = $DB->get_record('role', array('id'=>$USER->access['rsw'][$context->path]))) {
                    $rolename = ': '.role_get_name($role, $context);
                }
                $loggedinas = get_string('loggedinas', 'moodle', $username).$rolename;
                if ($withlinks) {
                    $url = new moodle_url('/course/switchrole.php', array('id'=>$course->id,'sesskey'=>sesskey(), 'switchrole'=>0, 'returnurl'=>$this->page->url->out_as_local_url(false)));
                    $loggedinas .= '('.html_writer::tag('a', get_string('switchrolereturn'), array('href'=>$url)).')';
                }
            } else {
                $loggedinas = $realuserinfo.get_string('loggedinas', 'moodle', $username);
                if ($withlinks) {
		  // Take out Logout link
		  //$loggedinas .= " (<a href=\"$CFG->wwwroot/login/logout.php?sesskey=".sesskey()."\">".get_string('logout').'</a>)';
                }
            }
        } else {
	  $loggedinas = html_writer::tag('a', get_string('loggedinnot', 'moodle'), array('href'=>$CFG->wwwroot.'/login/index.php'));
            if (!$loginpage && $withlinks) {
	      //$loggedinas .= " (<a href=\"$loginurl\">".get_string('login').'</a>)';
            }
        }

        //$loggedinas = '<div class="logininfo">'.$loggedinas.'</div>';
	//$loggedinas .= '<a href="http://dragonnet.ssis-suzhou.net"><img src="http://dragonnet.ssis-suzhou.net/pix/smallogono.gif" /></a>';

        if (isset($SESSION->justloggedin)) {
            unset($SESSION->justloggedin);
            if (!empty($CFG->displayloginfailures)) {
                if (!isguestuser()) {
                    if ($count = count_login_failures($CFG->displayloginfailures, $USER->username, $USER->lastlogin)) {
                        $loggedinas .= '&nbsp;<div class="loginfailures">';
                        if (empty($count->accounts)) {
                            $loggedinas .= get_string('failedloginattempts', '', $count);
                        } else {
                            $loggedinas .= get_string('failedloginattemptsall', '', $count);
                        }
                        if (file_exists("$CFG->dirroot/report/log/index.php") and has_capability('report/log:view', context_system::instance())) {
                            $loggedinas .= ' (<a href="'.$CFG->wwwroot.'/report/log/index.php'.
                                                 '?chooselog=1&amp;id=1&amp;modid=site_errors">'.get_string('logs').'</a>)';
                        }
                        $loggedinas .= '</div>';
                    }
                }
            }
        }

        return $loggedinas;
    }

    /**
     * Returns HTML to display a "Turn editing on/off" button in a form.
     *
     * @param moodle_url $url The URL + params to send through when clicking the button
     * @return string HTML the button
     */
    public function edit_button(moodle_url $url) {
        $url->param('sesskey', sesskey());
        if ($this->page->user_is_editing()) {
            $url->param('edit', 'off');
            $editstring = get_string('turneditingoff');
        } else {
            $url->param('edit', 'on');
            $editstring = get_string('turneditingon');
        }

        return $this->single_button($url, $editstring);
    }

    public function set_really_editing($editing) {
        $this->really_editing = $editing;
    }
    /**
     * Outputs the page's footer
     * @return string HTML fragment
     */
    public function footer() {
        global $CFG, $DB, $USER;

        $output = $this->container_end_all(true);

        $footer = $this->opencontainers->pop('header/footer');

        if (debugging() and $DB and $DB->is_transaction_started()) {
            // TODO: MDL-20625 print warning - transaction will be rolled back
        }

        // Provide some performance info if required
        $performanceinfo = '';
        if (defined('MDL_PERF') || (!empty($CFG->perfdebug) and $CFG->perfdebug > 7)) {
            $perf = get_performance_info();
            if (defined('MDL_PERFTOLOG') && !function_exists('register_shutdown_function')) {
                error_log("PERF: " . $perf['txt']);
            }
            if (defined('MDL_PERFTOFOOT') || debugging() || $CFG->perfdebug > 7) {
                $performanceinfo = decaf_performance_output($perf);
            }
        }

        $footer = str_replace($this->unique_performance_info_token, $performanceinfo, $footer);

        $footer = str_replace($this->unique_end_html_token, $this->page->requires->get_end_code(), $footer);

        $this->page->set_state(moodle_page::STATE_DONE);

        if(!empty($this->page->theme->settings->persistentedit) && property_exists($USER, 'editing') && $USER->editing && !$this->really_editing) {
            $USER->editing = false;
        }

        return $output . $footer;
    }

        /**
     * The standard tags (typically performance information and validation links,
     * if we are in developer debug mode) that should be output in the footer area
     * of the page. Designed to be called in theme layout.php files.
     * @return string HTML fragment.
     */
    public function standard_footer_html() {
        global $CFG;

        // This function is normally called from a layout.php file in {@link header()}
        // but some of the content won't be known until later, so we return a placeholder
        // for now. This will be replaced with the real content in {@link footer()}.
        $output = $this->unique_performance_info_token;
        // Moodle 2.1 uses a magic accessor for $this->page->devicetypeinuse so we need to
        // check for the existence of the function that uses as
        // isset($this->page->devicetypeinuse) returns false
        if ($this->page->devicetypeinuse=='legacy') {
            // The legacy theme is in use print the notification
            $output .= html_writer::tag('div', get_string('legacythemeinuse'), array('class'=>'legacythemeinuse'));
        }

        // Get links to switch device types (only shown for users not on a default device)
        $output .= $this->theme_switch_links();
        
       // if (!empty($CFG->debugpageinfo)) {
       //     $output .= '<div class="performanceinfo">This page is: ' . $this->page->debug_summary() . '</div>';
       // }
        if (debugging(null, DEBUG_DEVELOPER)) {  // Only in developer mode
            $output .= '<div class="purgecaches"><a href="'.$CFG->wwwroot.'/admin/purgecaches.php?confirm=1&amp;sesskey='.sesskey().'">'.get_string('purgecaches', 'admin').'</a></div>';
        }
        if (!empty($CFG->debugvalidators)) {
            $output .= '<div class="validators"><ul>
              <li><a href="http://validator.w3.org/check?verbose=1&amp;ss=1&amp;uri=' . urlencode(qualified_me()) . '">Validate HTML</a></li>
              <li><a href="http://www.contentquality.com/mynewtester/cynthia.exe?rptmode=-1&amp;url1=' . urlencode(qualified_me()) . '">Section 508 Check</a></li>
              <li><a href="http://www.contentquality.com/mynewtester/cynthia.exe?rptmode=0&amp;warnp2n3e=1&amp;url1=' . urlencode(qualified_me()) . '">WCAG 1 (2,3) Check</a></li>
            </ul></div>';
        }
        if (!empty($CFG->additionalhtmlfooter)) {
            $output .= "\n".$CFG->additionalhtmlfooter;
        }
        return $output;
    }


    protected function seek(&$branch) {
      foreach ($branch as $b) {
	  if ($b->categories) {
	      $this->seek($b->categories);
	  }

        foreach ( $b->courses as $course ) {
	    if (! array_key_exists($course->id, $this->user_courses)) {
	        unset($b->courses[$course->id]);
	    }
        }

      }
    }

    protected function ridof(&$branch) {
        for ($i = 0, $size = count($branch); $i < $size; $i++) {
	    if (isset($branch[$i]->categories) && ! empty($branch[$i]->categories)) {
	        $this->ridof($branch[$i]->categories);
	    }
	    if (isset($branch[$i]->depth) && empty($branch[$i]->courses) && empty($branch[$i]->categories)) {
	        unset($branch[$i]);
            }
	}
    }

    protected function howmany($branch) {
        $this_total = 0;
	if (! $branch) { return 0; }
        foreach ($branch as $b) {
	    if ($b->courses) {
	        $this_total += count($b->courses);
	    }
	    if ($b->categories) {
		$this_total += $this->howmany($b->categories); 
	    }
        }
	return $this_total;
    }

    protected function return_courses($branch) {
      if (! $branch) { return $branch; }
        $these_courses = array();
	foreach ($branch as $b) {
            if ($b->categories) {
	        foreach ($this->return_courses($b->categories) as $course) {
		    $these_courses[$course->id] = $course;
		    $these_courses[$course->id]->category = 50;
	        }
	    }
	    if ($b->courses) {
	        foreach ($b->courses as $course) {
	            $these_courses[$course->id] = $course;
		    $these_courses[$course->id]->category = 50;
	        }
            }
	}
	return $these_courses;
    }

    protected function spellout(&$branch) {
        global $DB;
        $results = $DB->get_records_sql('SELECT * FROM ssismdl_course_categories WHERE parent = ? ORDER BY sortorder', array('0'));

	// First, need to get the index of the smart tab
	$keys = array_keys($results);
	$smart_tab_index = null;
        for ($i = 0; $i < count($results); $i++) {
	  $this_one = $results[$keys[$i]];
	  if ($this_one->name == 'Teaching & Learning') {
	      $smart_tab_index = $i;
	  }
        }
	
	$maximumnumber = 20;

	if (isset($smart_tab_index) && array_key_exists($smart_tab_index, $branch)) {
	    $smart_tab_branch = array($branch[$smart_tab_index]);
	} else {
	    $smart_tab_branch = NULL;
	}
        if ($smart_tab_branch && $this->howmany($smart_tab_branch) <= 20) {
	    $branch[$smart_tab_index]->courses = $this->return_courses($smart_tab_branch);
	    $branch[$smart_tab_index]->categories = array();
	}
    }

    public function setup_courses() {
        if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
	    $this->user_courses = get_course_category_tree();
	} else {
            $this->my_courses = get_course_category_tree();
	    $this->all_courses = $this->my_courses;  // copies it
            $this->user_courses = enrol_get_my_courses('category', 'visible DESC, fullname ASC');
	    $this->seek($this->my_courses);
	    $this->ridof($this->my_courses);
	    $this->spellout($this->my_courses);
	}
    }

    protected function add_category_to_custom_menu_for_admins($menu, $category) {
        // We use a sort starting at a high value to ensure the category gets added to the end
        static $sort = 1000;
	$old_sort = $sort;
	$node = $menu->add($category->name, new moodle_url('/course/category.php', array('id' =>  $category->id)), NULL, NULL, $old_sort++);

        // Add subcategories to the category node by recursivily calling this method.
        $subcategories = $category->categories;
        foreach ($subcategories as $subcategory) {
            $this->add_category_to_custom_menu_for_admins($node, $subcategory);
        }

        // Now we add courses to the category node in the menu
        $courses = $category->courses;
        foreach ($courses as $course) {
            $node->add($course->fullname, new moodle_url('/course/view.php', array('id' => $course->id)), $course->fullname);
        }
	$sort = $old_sort + 2;
    }

    protected function add_to_custom_menu($menu, $cat_name, $array) {
	foreach ($array as $a) {
	    $categories_no_click = NULL; // no clicking, change this to a url if you want clicking

	    if ($a->name == 'Invisible') { continue; }

	    $node = $menu->add($icon.$a->name, $categories_no_click, NULL, NULL, $a->sortorder);
	    if ($a->name == 'Teaching & Learning') {
	        $this->teachinglearningnode = $node;
	    }

            $this->add_to_custom_menu($node, $a->name, $a->categories);
	    
            foreach ($a->courses as $course) {
	      $node->add($icon.$course->fullname, new moodle_url('/course/view.php', array('id' => $course->id)), $course->fullname);
            }
        }

    }

    /**
     * Renders a custom menu object (located in outputcomponents.php)
     *
     * The custom menu this method override the render_custom_menu function
     * in outputrenderers.php
     * @staticvar int $menucount
     * @param custom_menu $menu
     * @return string
     */
    protected function render_custom_menu(custom_menu $menu) {
        // If the menu has no children return an empty string
        if (isloggedin())

	    $this->setup_courses();

	    if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
	        foreach ($mycourses as $category) {
	            $this->add_category_to_custom_menu_for_admins($menu, $category);
	        }
	    } else {
	        $this->teachinglearningnode = NULL;
	        $this->add_to_custom_menu($menu, '', $this->my_courses);
	        if ($this->teachinglearningnode) {
	            $this->teachinglearningnode->add('Browse ALL Courses', new moodle_url('/course/category.php', array('id' => 50)), 'Browse ALL Courses');
	    }
        }
  
        if (!$menu->has_children()) {
            return '';
        }
        // Initialise this custom menu
        $content = html_writer::start_tag('ul', array('class'=>'dropdown dropdown-horizontal decaf-custom-menu'));
        // Render each child
        foreach ($menu->get_children() as $item) {
            $content .= $this->render_custom_menu_item($item);
        }

        // Close the open tags
        $content .= html_writer::end_tag('ul');
        // Return the custom menu
        return $content;
    }

    /**
     * Renders a pix_icon widget and returns the HTML to display it.
     *
     * @param pix_icon $icon
     * @return string HTML fragment
     */
    protected function render_pix_icon(pix_icon $icon) {
        $attributes = $icon->attributes;
        $attributes['src'] = $this->pix_url($icon->pix, $icon->component);
	//echo $icon->pix.' : ';
	switch ($icon->pix) {
	case 'i/navigationitem': return html_writer::tag('i', '', array('class'=>'icon-cog pull-left')); break;
	case 'i/edit': return html_writer::tag('i', '', array('class'=>'icon-edit pull-left')); break;
	case 'i/settings': return html_writer::tag('i', '', array('class'=>'icon-cogs pull-left')); break;
	case 'i/group': return html_writer::tag('i', '', array('class'=>'icon-group pull-left')); break;
	case 'i/filter': return html_writer::tag('i', '', array('class'=>'icon-filter pull-left')); break;
	case 'i/backup': return html_writer::tag('i', '', array('class'=>'icon-upload pull-left')); break;
	case 'i/import': return html_writer::tag('i', '', array('class'=>'icon-download pull-left')); break;
	case 'i/restore': return html_writer::tag('i', '', array('class'=>'icon-download pull-left')); break;
	case 'i/report': return html_writer::tag('i', '', array('class'=>'icon-file pull-left')); break;
	case 'i/permissions': return html_writer::tag('i', '', array('class'=>'icon-user-md pull-left')); break;
	case 'i/switchrole': return html_writer::tag('i', '', array('class'=>'icon-user pull-left')); break;
	case 'i/outcomes': return html_writer::tag('i', '', array('class'=>'icon-bar-chart pull-left')); break;
	case 'i/grades': return html_writer::tag('i', '', array('class'=>'icon-legal pull-left')); break;
	case 'i/publish': return html_writer::tag('i', '', array('class'=>'icon-globe pull-left')); break;
	case 'i/return': return html_writer::tag('i', '', array('class'=>'icon-rotate-left pull-left')); break;

	}
        return html_writer::empty_tag('img', $attributes);
    }


    /**
     * Renders a custom menu node as part of a submenu
     *
     * The custom menu this method override the render_custom_menu_item function
     * in outputrenderers.php
     *
     * @see render_custom_menu()
     *
     * @staticvar int $submenucount
     * @param custom_menu_item $menunode
     * @return string
     */
    protected function render_custom_menu_item(custom_menu_item $menunode) {
        // Required to ensure we get unique trackable id's
        static $submenucount = 0;
        $content = html_writer::start_tag('li');
	switch ($menunode->get_text()) {
	case 'DragonNet': $content .= html_writer::tag('i', '', array('class'=>'icon-location-arrow pull-left')); break;
	case 'Facilities Bookings': $content .= html_writer::tag('i', '', array('class'=>'icon-ticket pull-left')); break;
	case 'Surveys': $content .= html_writer::tag('i', '', array('class'=>'icon-tasks pull-left')); break;
	case 'Directory': $content .= html_writer::tag('i', '', array('class'=>'icon-info-sign pull-left')); break;
	case 'DragonTV': $content .= html_writer::tag('i', '', array('class'=>'icon-facetime-video pull-left')); break;
	case 'Help': $content .= html_writer::tag('i', '', array('class'=>'icon-phone pull-left')); break; /* l.;;. */
	case 'Documents': $content .= html_writer::tag('i', '', array('class'=>'icon-file-alt pull-left')); break;
	case 'Teaching & Learning': $content .= html_writer::tag('i', '', array('class'=>'icon-magic pull-left')); break;
	case 'Groups': $content .= html_writer::tag('i', '', array('class'=>'icon-rocket pull-left')); break;
	}
        if ($menunode->has_children()) {
            // If the child has menus render it as a sub menu
            $submenucount++;
            $extra = '';
            if ($menunode->get_url() === null) {
                $extra = ' customitem-nolink';
            }
            $content .= html_writer::start_tag('span', array('class'=>'customitem'.$extra));
            if ($menunode->get_url() !== null) {
                $content .= html_writer::link($menunode->get_url(), $menunode->get_text(), array('title'=>$menunode->get_title()));
            } else {
                $content .= $menunode->get_text();
            }

	    if ($parent = $menunode->get_parent()) {
	      // don't use the icon if we're at the top of the chain
	      if (!$parent->get_text() === 'root') {
  	          $content .= html_writer::tag('i', '', array('class'=>'pull-right icon-caret-right'));            
	      }
	    }
            $content .= html_writer::end_tag('span');
            $content .= html_writer::start_tag('ul');
            foreach ($menunode->get_children() as $menunode) {
                $content .= $this->render_custom_menu_item($menunode);
            }
            $content .= html_writer::end_tag('ul');
        } else {
            // The node doesn't have children so produce a final menuitem

            if ($menunode->get_url() !== null) {
                $url = $menunode->get_url();
            } else {
                $url = '#';
            }
            $content .= html_writer::link($url, $menunode->get_text(), array('title'=>$menunode->get_title(), 'class'=>'customitem-no-children'));
        }

        $content .= html_writer::end_tag('li');
        // Return the sub menu
        return $content;
    }

    // Copied from core_renderer with one minor change - changed $this->output->render() call to $this->render()
    protected function render_navigation_node(navigation_node $item) {
        $content = $item->get_content();
        $title = $item->get_title();
        if ($item->icon instanceof renderable && !$item->hideicon) {
                $icon = $this->render($item->icon);
            $content = $icon.$content; // use CSS for spacing of icons
        }
        if ($item->helpbutton !== null) {
            $content = trim($item->helpbutton).html_writer::tag('span', $content, array('class'=>'clearhelpbutton'));
        }
        if ($content === '') {
            return '';
        }

        if ($item->action instanceof action_link) {
            //TODO: to be replaced with something else
            $link = $item->action;
            if ($item->hidden) {
                $link->add_class('dimmed');
            }
            $content = $this->render($link);
        } else if ($item->action instanceof moodle_url) {
            $attributes = array();
            if ($title !== '') {
                $attributes['title'] = $title;
            }
            if ($item->hidden) {
                $attributes['class'] = 'dimmed_text';
            }
            $content = html_writer::link($item->action, $content, $attributes);

        } else if (is_string($item->action) || empty($item->action)) {
            $attributes = array();
            if ($title !== '') {
                $attributes['title'] = $title;
            }
            if ($item->hidden) {
                $attributes['class'] = 'dimmed_text';
            }
            $content = html_writer::tag('span', $content, $attributes);
        }

        return $content;
    }

    /**
     * blocks_for_region() overrides core_renderer::blocks_for_region()
     *  in moodlelib.php. Returns a string
     * values ready for use.
     *
     * @return string
     */
    public function blocks_for_region($region) {
        if (!$this->page->blocks->is_known_region($region)) {
            return '';
        }
        $blockcontents = $this->page->blocks->get_content_for_region($region, $this);
        $output = '';
        foreach ($blockcontents as $bc) {
            if ($bc instanceof block_contents) {
                if (!($this->page->theme->settings->hidesettingsblock && substr($bc->attributes['class'], 0, 15) == 'block_settings ')
                        && !($this->page->theme->settings->hidenavigationblock && substr($bc->attributes['class'], 0, 17) == 'block_navigation ')) {
                    $output .= $this->block($bc, $region);
                }
            } else if ($bc instanceof block_move_target) {
                $output .= $this->block_move_target($bc);
            } else {
                throw new coding_exception('Unexpected type of thing (' . get_class($bc) . ') found in list of block contents.');
            }
        }
        return $output;
    }

}

class theme_decaf_topsettings_renderer extends plugin_renderer_base {

    public function settings_tree(settings_navigation $navigation) {
        global $CFG;
        return $this->navigation_node($navigation, array('class' => 'dropdown  dropdown-horizontal'));
    }

    public function settings_search_box() {
        global $CFG;
	global $USER;
        // TODO: Make this a typical profile thing ssis user menu
	// Calendar button
        $content .= html_writer::start_tag('ul', array('class'=>'topadminsearchform dropdown dropdown-horizontal'));
        $content .=  html_writer::start_tag('li');
	$content .= html_writer::tag('i', '', array('class'=>'icon-user pull-left'));
	$content .= $this->login_info();
	
	// Beginning of SSIS's special user menu
	$content .= html_writer::start_tag('ul');
	if (isloggedin()) {
	    $content .= html_writer::start_tag('li');
	    $content .= html_writer::tag('a', html_writer::tag('i', '', array('class'=>'icon-edit pull-left')).'Edit Profile', array('href'=>"$CFG->wwwroot/user/editadvanced.php?id=$USER->id&course=1"));
	    $content .= html_writer::end_tag('li');

	    $content .= html_writer::start_tag('li');
	    $content .= html_writer::tag('a', html_writer::tag('i', '', array('class'=>'icon-edit pull-left')).'Change password', array('href'=>"$CFG->wwwroot/login/change_password.php?id=1"));
	    $content .= html_writer::end_tag('li');

	    $content .= html_writer::start_tag('li');
	    $content .= html_writer::tag('a', html_writer::tag('i', '', array('class'=>'icon-off pull-left')).'Logout', array('href'=>$CFG->wwwroot.'/login/logout.php?sesskey='.sesskey()));
	    $content .= html_writer::end_tag('li');
	}
	$content .= html_writer::end_tag('ul');  // end submenu

	$content .= html_writer::end_tag('li');
	$content .= html_writer::end_tag('ul');
	return $content;
    }

    public function navigation_tree(global_navigation $navigation) {
        global $CFG;
	global $USER;

	//$this->setup_courses();

        $content = html_writer::start_tag('ul', array('class' => 'dropdown dropdown-horizontal'));
        $content .= html_writer::start_tag('li');
	$icon = html_writer::tag('i', '', array('class'=>'icon-plane pull-left'));
	$content .= html_writer::tag('span', $icon.'Navigate');
	$content .= html_writer::start_tag('ul');

	$content .= $this->navbuttons();
	   
	$content .= html_writer::end_tag('li');
	$content .= html_writer::end_tag('ul');
	    
	    //$content .= html_writer::start_tag('ul');

	    //$this->setup_courses();

	    //include_once($CFG->dirroot.'/calendar/lib.php');
	    //$days_ahead = 30;
	    //$cal_items = calendar_get_upcoming($this->user_courses, true, $USER->id, $days_ahead);

	$content .= html_writer::end_tag('ul');
	
        return $content;
    }

    protected function navigation_node(navigation_node $node, $attrs=array()) {
        global $PAGE;
        static $subnav;
        $items = $node->children;
        $hidecourses = (property_exists($PAGE->theme->settings, 'coursesloggedinonly') && $PAGE->theme->settings->coursesloggedinonly && !isloggedin());

        // exit if empty, we don't want an empty ul element
        if ($items->count() == 0) {
            return '';
        }

        // array of nested li elements
        $lis = array();
        foreach ($items as $item) {
            if (!$item->display) {
                continue;
            }
            if ($item->key === 'courses' && $hidecourses) {
                continue;
            }

            // Skip pointless "Current course" node, go straight to its last (sole) child
            if ($item->key === 'currentcourse') {
                $item = $item->children->last();
            }

            $isbranch = ($item->children->count() > 0 || $item->nodetype == navigation_node::NODETYPE_BRANCH || (property_exists($item, 'isexpandable') && $item->isexpandable));
            $hasicon = (!$sbranch && $item->icon instanceof renderable);

            if ($isbranch) {
                $item->hideicon = true;
            }
	    
            $content = $this->output->render($item);

	    // Gets rid of "My profile settings" since we put it all in the user menu anyway
	    if ($item->get_content() === 'My profile settings') {
	        continue;
	    } 

	    switch ($item->get_content()) {
	    case 'Site administration': $content = html_writer::tag('i', '', array('class'=>'icon-wrench pull-left')).$content; break;
	    case 'Course administration': $content = html_writer::tag('i', '', array('class'=>'icon-wrench pull-left')).$content; break;
	    case 'My profile settings': $content = html_writer::tag('i', '', array('class'=>'icon-wrench pull-left')).$content; break;
	    case 'Switch role to...': $content = html_writer::tag('i', '', array('class'=>'icon-wrench pull-left')).$content; break;
	    case 'Front page settings': $content = html_writer::tag('i', '', array('class'=>'icon-wrench pull-left')).$content; break;
	    }

            if($isbranch && $item->children->count()==0) {
                // Navigation block does this via AJAX - we'll merge it in directly instead
                if(!$subnav) {
                    // Prepare dummy page for subnav initialisation
                    $dummypage = new decaf_dummy_page();
                    $dummypage->set_context($PAGE->context);
                    $dummypage->set_url($PAGE->url);

                    $subnav = new decaf_expand_navigation($dummypage, $item->type, $item->key);
                } else {
                    // re-use subnav so we don't have to reinitialise everything
                    $subnav->expand($item->type, $item->key);
                }
                if (!isloggedin() || isguestuser()) {
                    $subnav->set_expansion_limit(navigation_node::TYPE_COURSE);
                }
                $branch = $subnav->find($item->key, $item->type);
                if($branch!==false) $content .= $this->navigation_node($branch);
            } else {
                $content .= $this->navigation_node($item);
            }

            if($isbranch && !($item->parent->parent==null)) { // && !(is_string($item->action) || empty($item->action))) {
	      // TODO: Have to do the ugly thing here and take out the last part of the </a> tag for it to work better
	        $content = html_writer::tag('li', $content.'<i class="pull-right icon-caret-right"></i>', array('class' => 'clickable-with-children'));
            } else {
                $content = html_writer::tag('li', $content);
            }
            $lis[] = $content;
        }

        if (count($lis)) {
            return html_writer::nonempty_tag('ul', implode("\n", $lis), $attrs);
        } else {
            return '';
        }
    }

    public function search_form(moodle_url $formtarget, $searchvalue) {
      //global $CFG;
        //$content .= html_writer::start_tag('span', array('id' =>'awesomeNavMenu'));

        //$content .= html_writer::start_tag('li');
        $content = html_writer::start_tag('span', array('class' =>'topadminsearchform'));
        //$content .= html_writer::empty_tag('img', array('alt' => '', 'src' =>$this->pix_url('user_silhouette', 'theme')));
	$content .= $this->login_info();
        $content .= html_writer::end_tag('span');
        //$content .= html_writer::end_tag('li');

	return $content;

        //if (empty($searchvalue)) {
        //    $searchvalue = 'Search Settings..';
        //}

        //$content = html_writer::start_tag('form', array('class' => 'topadminsearchform', 'method' => 'get', 'action' => $formtarget));
        //$content .= html_writer::start_tag('div', array('class' => 'search-box'));
        //$content .= html_writer::tag('label', s(get_string('searchinsettings', 'admin')), array('for' => 'adminsearchquery', 'class' => 'accesshide'));
        //$content .= html_writer::empty_tag('input', array('id' => 'topadminsearchquery', 'type' => 'text', 'name' => 'query', 'value' => s($searchvalue),
	//           'onfocus' => "if(this.value == 'Search Settings..') {this.value = '';}",
	//          'onblur' => "if (this.value == '') {this.value = 'Search Settings..';}"));
        //$content .= html_writer::empty_tag('input', array('class'=>'search-go','type'=>'submit', 'value'=>''));
        //$content .= html_writer::end_tag('div');
        //$content .= html_writer::end_tag('form');

        //return $content;
    }

}

?>
