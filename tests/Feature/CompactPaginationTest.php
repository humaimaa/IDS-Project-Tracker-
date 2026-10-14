<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CompactPaginationTest extends TestCase
{
    #[TestWith([5, 1, [1, 2, 3, 4, 5], 0])]
    #[TestWith([6, 1, [1, 2, 5, 6], 1])]
    #[TestWith([10, 3, [1, 2, 3, 9, 10], 1])]
    #[TestWith([10, 4, [1, 2, 4, 9, 10], 2])]
    #[TestWith([10, 5, [1, 2, 5, 9, 10], 2])]
    #[TestWith([10, 8, [1, 2, 8, 9, 10], 1])]
    #[TestWith([10, 10, [1, 2, 9, 10], 1])]
    public function test_numbered_links_show_the_current_page_and_ends_with_dots_only_between_gaps(int $lastPage, int $currentPage, array $expectedPages, int $dots): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), $lastPage * 10, 10, $currentPage, ['path' => '/projects', 'query' => ['partner' => 'ADB']]);
        $html = $paginator->links()->toHtml();
        preg_match_all('/aria-label="Page (\d+)"/', $html, $matches);
        $this->assertSame($expectedPages, array_map('intval', $matches[1]));
        $this->assertSame($dots, substr_count($html, '&hellip;'));
        $this->assertStringContainsString('aria-current="page" aria-label="Page '.$currentPage.'"', $html);
        $this->assertStringContainsString('>'.$currentPage.'</strong> of '.$lastPage, $html);
        $this->assertStringContainsString('partner=ADB', $html);
    }

    public function test_middle_pages_remain_reachable_and_filters_are_preserved(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 100, 10, 5, ['path' => '/projects', 'query' => ['partner' => 'ADB', 'health' => 'Off track']]);
        $html = $paginator->links()->toHtml();
        $this->assertStringContainsString('href="'.e($paginator->url(4)).'" rel="prev"', $html);
        $this->assertStringContainsString('href="'.e($paginator->url(6)).'" rel="next"', $html);
    }

    public function test_first_and_last_pages_disable_unavailable_navigation(): void
    {
        $first = (new LengthAwarePaginator(range(1, 10), 60, 10, 1))->links()->toHtml();
        $last = (new LengthAwarePaginator(range(1, 10), 60, 10, 6))->links()->toHtml();
        $this->assertStringNotContainsString('rel="prev"', $first);
        $this->assertStringContainsString('rel="next"', $first);
        $this->assertStringContainsString('aria-current="page" aria-label="Page 1"', $first);
        $this->assertStringNotContainsString('rel="next"', $last);
        $this->assertStringContainsString('rel="prev"', $last);
        $this->assertStringContainsString('aria-current="page" aria-label="Page 6"', $last);
    }

    #[TestWith([0])]
    #[TestWith([10])]
    public function test_empty_and_single_page_lists_do_not_show_pagination(int $total): void
    {
        $paginator = new LengthAwarePaginator([], $total, 10, 1);
        $this->assertSame('', trim($paginator->links()->toHtml()));
    }
}
