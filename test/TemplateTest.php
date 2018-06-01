<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use DDL\Feather\Template;

/**
 * TemplateTest
 * Tests the Template object
 *
 * @covers Template
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
final class TemplateTest extends TestCase
{
    public function testVariables()
    {
        $template = Template::instance(YES);
        $template->loadTemplate(__DIR__ . "/template.html", "Test", "TestVars");
        $template->assign(["Variable" => "foobar"], "Test");
        $this->assertEquals($template->render("Test"), "Static Text. foobar");
    }

    public function testLiterals()
    {
        $template = Template::instance(YES);
        $template->loadTemplate(__DIR__ . "/template.html", "Test", "TestLiterals");
        $this->assertEquals($template->render("Test"), "Static Text. Literal String");
    }

    public function testMaskedLiterals()
    {
        $template = Template::instance(YES);
        $template->loadTemplate(__DIR__ . "/template.html", "Test", "TestMaskedLiterals");
        $template->addMask("masked", "strtolower");
        $this->assertEquals($template->render("Test"), "Static Text. literal string");
    }
}
