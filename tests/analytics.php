<?php
/**
 * Copyright 2015 Bit API Hub
 * 
 * PHPUnit Tests
 */

namespace Test;

/**
 * Unit tests for \Segment\Analytics
 * 
 * @group App
 * @group Packages
 * @group Analytics
 * @group Segment
 */
class Analytics extends \TestCase
{
	public static function setUpBeforeClass()
	{
		\Package::load('segment');
	}
	
	public function test_set_user_id()
	{
		$analytics = \Segment\Analytics::instance();
		
		$test_subject = $analytics->set_user_id('test_user');
		
		$this->assertSame('test_user', \Session::get('segment.identity.userId', false));
	}
	
	public function test_page()
	{
		$page_data = array(
			
			'category'	=> 'Test',
			'name'		=> 'Page',
			
		);
		
		$analytics = \Segment\Analytics::instance();
		
		$test_subject_a = $analytics->page($page_data, false);
		$test_subject_b = $analytics->page($page_data);
		
		$this->assertSame(true, $test_subject_a);
		$this->assertInternalType('string', $test_subject_b);
	}
	
	public function test_alias()
	{
		$analytics = \Segment\Analytics::instance();
		
		$test_subject_a = $analytics->alias(array(), false);
		$test_subject_b = $analytics->alias();
		
		$this->assertSame(true, $test_subject_a);
		$this->assertInternalType('string', $test_subject_b);
	}
	
	public function test_identify()
	{
		$identify_data['traits'] = array('george' => 'of the jungle');
		
		$analytics = \Segment\Analytics::instance();
		
		$test_subject_a = $analytics->identify($identify_data, false);
		$test_subject_b = $analytics->identify($identify_data);
		
		$this->assertSame(true, $test_subject_a);
		$this->assertInternalType('string', $test_subject_b);
	}
	
	public function test_group()
	{
		$group_data = array(
			
			'groupId'	=> 'test group',
			'traits'	=> array(
				
				'priests' => 'launching missals'
			
			),
			
		);
		
		$analytics = \Segment\Analytics::instance();
		
		$test_subject_a = $analytics->group($group_data, false);
		$test_subject_b = $analytics->group($group_data);
		
		$this->assertSame(true, $test_subject_a);
		$this->assertInternalType('string', $test_subject_b);
	}
	
	public function test_track()
	{
		$track_data = array(
			
			'event'	=> 'Missal Launch',
			'properties'	=> array(
				
				'missal' => 'liturgical book',
				'priest' => 'weird person who launches missals',
			
			),
			
		);
		
		$analytics = \Segment\Analytics::instance();
		
		$test_subject_a = $analytics->track($track_data, false);
		$test_subject_b = $analytics->track($track_data);
		
		$this->assertSame(true, $test_subject_a);
		$this->assertInternalType('string', $test_subject_b);
	}
	
	public function test_custom()
	{
		$analytics = \Segment\Analytics::instance();
		
		$test_subject = $analytics->custom('function(){}');
		
		$this->assertSame('function(){}', $test_subject);
	}
	
	public function test_render()
	{
		$analytics = \Segment\Analytics::instance();
		
		$test_subject = $analytics->render();

		$this->assertInternalType('string', $test_subject);
	}
}
