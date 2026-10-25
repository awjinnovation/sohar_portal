<?php

namespace Tests\Feature\Discover;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    #[Test]
    public function it_keeps_the_allowed_subset_and_strips_the_rest(): void
    {
        $this->assertNull(HtmlSanitizer::clean('  '));
        $this->assertNull(HtmlSanitizer::clean('<p><br></p><script>x</script>'));
        $this->assertSame('<p>Line 1<br>Line 2</p><p>Two &amp; x &lt; y</p>', HtmlSanitizer::clean("Line 1\nLine 2\n\nTwo & x < y"));
        $this->assertSame('<p>Hello <b>x</b></p><ul><li>a</li></ul>', HtmlSanitizer::clean('Hello <b>x</b><ul><li>a</li></ul>'));
        $this->assertSame('<p>ok <strong>b</strong> <em>i</em></p><ul><li>a</li></ul><h3>T</h3>',
            HtmlSanitizer::clean('<div style="color:red" class="x">ok <strong onclick="y()">b</strong> <em>i</em></div><ul><li>a</li></ul><h1>T</h1>'));
        $this->assertSame('<p>x</p>', HtmlSanitizer::clean('<p>x</p><style>p{}</style><iframe src="//e"></iframe><!-- c -->'));
        $this->assertSame('<p>hi</p>', HtmlSanitizer::clean('<p><span><font>hi</font></span></p>'));
        $this->assertSame('<p>a <a href="mailto:a@b.om" rel="noopener nofollow" target="_blank">m</a> j</p>',
            HtmlSanitizer::clean('<p>a <a href="mailto:a@b.om" onmouseover="x">m</a> <a href="java&#09;script:alert(1)">j</a></p>'));
        $this->assertSame('<p>عربي <b>نص</b></p>', HtmlSanitizer::clean('<p>عربي <b>نص</b></p>'));
        $this->assertSame('a b c', HtmlSanitizer::toText('<p>a</p><ul><li>b</li><li>c</li></ul>'));
    }
}
