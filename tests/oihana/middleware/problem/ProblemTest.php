<?php

namespace tests\oihana\middleware\problem ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\problem\Problem ;

/**
 * Unit coverage for {@see \oihana\middleware\problem\Problem}.
 */
class ProblemTest extends TestCase
{
    public function testEmptyProblemSerialisesToEmptyArray() :void
    {
        $this->assertSame( [] , new Problem()->toArray() ) ;
    }

    public function testStandardFieldsKeepRfcOrder() :void
    {
        $problem = new Problem
        (
            type     : 'https://example.com/probs/x' ,
            title    : 'Bad' ,
            status   : 400 ,
            detail   : 'Bad input' ,
            instance : '/foo' ,
        ) ;

        $this->assertSame
        ( [
            'type'     => 'https://example.com/probs/x' ,
            'title'    => 'Bad' ,
            'status'   => 400 ,
            'detail'   => 'Bad input' ,
            'instance' => '/foo' ,
          ] ,
          $problem->toArray() ,
        ) ;
    }

    public function testNullStandardFieldsAreOmitted() :void
    {
        $problem = new Problem( title : 'Bad' , status : 422 ) ;

        $this->assertSame
        ( [
            'title'  => 'Bad' ,
            'status' => 422 ,
          ] ,
          $problem->toArray() ,
        ) ;
    }

    public function testExtensionsAreAppendedAfterStandardFields() :void
    {
        $problem = new Problem
        (
            title      : 'Bad' ,
            status     : 422 ,
            extensions :
            [
                'field' => 'email' ,
                'value' => 'jane@example.com' ,
            ] ,
        ) ;

        $this->assertSame
        ( [
            'title'  => 'Bad' ,
            'status' => 422 ,
            'field'  => 'email' ,
            'value'  => 'jane@example.com' ,
          ] ,
          $problem->toArray() ,
        ) ;
    }

    public function testExtensionsCollidingWithStandardFieldsAreDropped() :void
    {
        // Per RFC 9457 §3.2, extensions MUST NOT shadow standard fields.
        $problem = new Problem
        (
            title      : 'Real Title' ,
            extensions :
            [
                'title'  => 'Should be dropped' ,
                'status' => 999 ,
                'safe'   => 'kept' ,
            ] ,
        ) ;

        $this->assertSame
        ( [
            'title' => 'Real Title' ,
            'safe'  => 'kept' ,
          ] ,
          $problem->toArray() ,
        ) ;
    }

    public function testEmptyExtensionsAreSilentlyIgnored() :void
    {
        $problem = new Problem( title : 'Bad' , extensions : [] ) ;

        $this->assertSame( [ 'title' => 'Bad' ] , $problem->toArray() ) ;
    }
}
