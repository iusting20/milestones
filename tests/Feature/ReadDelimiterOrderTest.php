<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadDelimiterOrderTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attrs = []): Book
    {
        return Book::query()->create(array_merge([
            'title' => 'Book',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ], $attrs));
    }

    private function delimiter(array $attrs = []): Book
    {
        return $this->book(array_merge([
            'title' => '',
            'is_delimiter' => true,
        ], $attrs));
    }

    public function test_reordering_a_divider_to_the_last_place_deletes_it(): void
    {
        $a = $this->book(['title' => 'A', 'position' => 1]);
        $b = $this->book(['title' => 'B', 'position' => 2]);
        $divider = $this->delimiter(['position' => 3]);

        Livewire::test(Read::class)
            ->call('reorderBooks', [$a->id, $divider->id, $b->id]);

        $this->assertNotNull($divider->fresh());
        $this->assertSame([1, 2, 3], [$a->fresh()->position, $divider->fresh()->position, $b->fresh()->position]);

        Livewire::test(Read::class)
            ->call('reorderBooks', [$a->id, $b->id, $divider->id]);

        $this->assertNull($divider->fresh());
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_articles_also_drop_a_trailing_divider(): void
    {
        $article = $this->book([
            'title' => 'Essay',
            'kind' => Book::KIND_ARTICLE,
            'position' => 1,
        ]);
        $divider = $this->delimiter([
            'kind' => Book::KIND_ARTICLE,
            'position' => 2,
        ]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])
            ->call('reorderBooks', [$article->id, $divider->id]);

        $this->assertNull($divider->fresh());
        $this->assertNull(Book::query()->where('kind', Book::KIND_ARTICLE)->where('is_delimiter', true)->first());
    }

    public function test_finishing_the_last_book_deletes_a_now_trailing_divider(): void
    {
        $a = $this->book(['title' => 'Keep', 'position' => 1]);
        $divider = $this->delimiter(['position' => 2]);
        $last = $this->book(['title' => 'Done', 'position' => 3]);

        Livewire::test(Read::class, ['selectedBookId' => $last->id])
            ->call('markBookFinished', $last->id);

        $this->assertNull($divider->fresh());
        $this->assertSame(Book::STATUS_FINISHED, $last->fresh()->status);
        $this->assertSame(Book::STATUS_READING, $a->fresh()->status);
    }

    public function test_add_delimiter_link_sits_below_the_list_and_abc_is_books_only(): void
    {
        $this->book(['title' => 'Open Book']);

        $books = Livewire::test(Read::class)->html();
        $this->assertNotFalse(strpos($books, 'bookSortList()'));
        $listEnd = strpos($books, '</ul>');
        $abc = strpos($books, '>A/B/C</button>');
        $dash = strpos($books, 'aria-label="Add delimiter"');
        $this->assertNotFalse($listEnd);
        $this->assertNotFalse($abc);
        $this->assertNotFalse($dash);
        $this->assertTrue($listEnd < $abc && $abc < $dash, 'A/B/C should sit below the list, left of ---');
        $this->assertStringContainsString('justify-end', $books);

        $articles = Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])->html();
        $this->assertStringContainsString('aria-label="Add delimiter"', $articles);
        $this->assertStringNotContainsString('>A/B/C</button>', $articles);
        $this->assertStringContainsString('justify-end', $articles);
    }

    public function test_abc_sort_groups_open_books_and_replaces_dividers(): void
    {
        $c1 = $this->book(['title' => 'C-first', 'type' => 'C', 'position' => 1]);
        $oldDivider = $this->delimiter(['position' => 2]);
        $a2 = $this->book(['title' => 'A-second', 'type' => 'A', 'position' => 3]);
        $b1 = $this->book(['title' => 'B-only', 'type' => 'B', 'position' => 4]);
        $a1 = $this->book(['title' => 'A-first', 'type' => 'A', 'position' => 5]);
        $untyped = $this->book(['title' => 'No type', 'type' => null, 'position' => 6]);
        $finished = $this->book([
            'title' => 'Finished C',
            'type' => 'C',
            'status' => Book::STATUS_FINISHED,
            'position' => 7,
        ]);
        $extraDivider = $this->delimiter(['position' => 8]);

        Livewire::test(Read::class)->call('sortBooksByType');

        $aFirst = $a2->fresh();
        $aSecond = $a1->fresh();
        $this->assertSame(1, $aFirst->position, 'relative order of type A is kept');
        $this->assertSame(2, $aSecond->position);

        $dividers = Book::query()->where('kind', Book::KIND_BOOK)->where('is_delimiter', true)->orderBy('position')->get();
        $this->assertCount(2, $dividers);
        $this->assertSame(3, $dividers[0]->position);
        $this->assertSame(5, $dividers[1]->position);
        $this->assertSame(4, $b1->fresh()->position);
        $this->assertSame(6, $c1->fresh()->position);
        $this->assertSame(7, $untyped->fresh()->position);
        $this->assertSame(7, $finished->fresh()->position);
        $this->assertSame(Book::STATUS_FINISHED, $finished->fresh()->status);
        $this->assertTrue($dividers->contains('id', $oldDivider->id), 'existing dividers are reused when possible');
    }

    public function test_abc_sort_skips_empty_groups_and_never_leaves_edge_dividers(): void
    {
        $c = $this->book(['title' => 'C', 'type' => 'C', 'position' => 1]);
        $a = $this->book(['title' => 'A', 'type' => 'A', 'position' => 2]);
        $this->delimiter(['position' => 3]);
        $this->delimiter(['position' => 4]);

        Livewire::test(Read::class)->call('sortBooksByType');

        $open = Book::query()->where('kind', Book::KIND_BOOK)
            ->where(function ($query) {
                $query->where('is_delimiter', true)->orWhere('status', '!=', Book::STATUS_FINISHED);
            })
            ->orderBy('position')
            ->get();

        $this->assertSame(['A', 'divider', 'C'], $open->map(fn (Book $book) => $book->isDelimiter() ? 'divider' : $book->title)->all());
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(3, $c->fresh()->position);
        $this->assertCount(1, $open->filter->isDelimiter());

        $onlyB = $this->book(['title' => 'Solo B', 'type' => 'B', 'position' => 1, 'kind' => Book::KIND_ARTICLE]);
        $this->delimiter(['position' => 2, 'kind' => Book::KIND_ARTICLE]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])->call('sortBooksByType');

        $this->assertNotNull($onlyB->fresh());
        $this->assertNotNull(Book::query()->where('kind', Book::KIND_ARTICLE)->where('is_delimiter', true)->first(), 'articles are not A/B/C-sorted');
    }

    public function test_add_delimiter_is_not_left_trailing(): void
    {
        $first = $this->book(['title' => 'First', 'position' => 1]);
        $second = $this->book(['title' => 'Second', 'position' => 2]);

        Livewire::test(Read::class, ['selectedBookId' => $second->id])
            ->call('addDelimiter');

        $open = Book::query()
            ->where('kind', Book::KIND_BOOK)
            ->where(function ($query) {
                $query->where('is_delimiter', true)->orWhere('status', '!=', Book::STATUS_FINISHED);
            })
            ->orderBy('position')
            ->get();

        $this->assertFalse($open->last()->isDelimiter(), 'adding a divider must not leave it last');
        $this->assertTrue($open->contains(fn (Book $book) => $book->isDelimiter()));
        $this->assertSame('Second', $open->last()->title);
        $this->assertSame(1, $first->fresh()->position);
    }
}
