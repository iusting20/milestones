<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Models\Book;
use App\Models\BookFeedbackIdea;
use App\Models\BookFeedbackSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadFeedbackIdeasTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attrs = []): Book
    {
        return Book::query()->create(array_merge([
            'title' => 'Finished Book',
            'status' => Book::STATUS_FINISHED,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ], $attrs));
    }

    public function test_description_tab_has_a_full_width_ideas_button_and_a_general_section(): void
    {
        $book = $this->book();

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('openFeedback', $book->id)
            ->assertSee('View ideas by chapter')
            ->assertSeeHtml('w-full')
            ->assertSet('feedbackChapterKey', 'general')
            ->assertSee('General')
            ->assertSee('← Back to Ideas', false)
            ->assertDontSeeHtml('autofocus');
    }

    public function test_saving_section_ideas_persists_them_and_counts_as_feedback(): void
    {
        $book = $this->book(['title' => 'Atomic Habits']);

        $component = Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee('Give feedback')
            ->call('openFeedback', $book->id)
            ->call('addFeedbackIdea', 'general')
            ->set('feedbackChapters.0.ideas.0.body', 'A general takeaway')
            ->call('addFeedbackSection', 'The 2-Minute Rule')
            ->assertSet('feedbackChapterKey', 'new-1')
            ->call('addFeedbackIdea', 'new-1')
            ->set('feedbackChapters.1.ideas.0.body', 'Start tiny')
            ->call('addFeedbackIdea', 'new-1')
            ->set('feedbackChapters.1.ideas.1.body', '   ')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved')
            ->assertSet('feedbackDirty', false)
            ->assertSee('View feedback')
            ->assertDontSee('Give feedback');

        $this->assertTrue($book->fresh()->hasFeedback());
        $this->assertDatabaseHas('book_feedback_ideas', [
            'book_id' => $book->id,
            'section_id' => null,
            'body' => 'A general takeaway',
        ]);
        $section = BookFeedbackSection::query()->where('book_id', $book->id)->first();
        $this->assertNotNull($section);
        $this->assertSame('The 2-Minute Rule', $section->title);
        $this->assertDatabaseHas('book_feedback_ideas', [
            'book_id' => $book->id,
            'section_id' => $section->id,
            'body' => 'Start tiny',
        ]);
        $this->assertSame(2, BookFeedbackIdea::query()->where('book_id', $book->id)->count());
        $this->assertSame('sec-'.$section->id, $component->get('feedbackChapterKey'));
        $component->assertSee('The 2-Minute Rule')
            ->assertSeeHtml('(1)');
    }

    public function test_blank_section_titles_are_dropped_and_ideas_alone_mark_feedback(): void
    {
        $book = $this->book();

        $component = Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('openFeedback', $book->id)
            ->call('addFeedbackSection', '   ');

        $this->assertCount(1, $component->get('feedbackChapters'));

        $component->call('addFeedbackSection', 'Keep me')
            ->set('feedbackChapters.1.title', '   ')
            ->call('addFeedbackIdea', 'general')
            ->set('feedbackChapters.0.ideas.0.body', 'Only idea')
            ->call('saveFeedback')
            ->assertSee('View feedback');

        $this->assertDatabaseCount('book_feedback_sections', 0);
        $this->assertDatabaseHas('book_feedback_ideas', [
            'book_id' => $book->id,
            'section_id' => null,
            'body' => 'Only idea',
        ]);
    }

    public function test_removing_a_section_drops_its_ideas_on_save(): void
    {
        $book = $this->book();

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('openFeedback', $book->id)
            ->call('addFeedbackSection', 'Soon gone')
            ->call('addFeedbackIdea', 'new-1')
            ->set('feedbackChapters.1.ideas.0.body', 'Temporary')
            ->call('saveFeedback')
            ->call('removeFeedbackSection', 'sec-'.BookFeedbackSection::query()->value('id'))
            ->assertSet('feedbackChapterKey', 'general')
            ->call('saveFeedback');

        $this->assertDatabaseCount('book_feedback_sections', 0);
        $this->assertDatabaseCount('book_feedback_ideas', 0);
        $this->assertFalse($book->fresh()->hasFeedback());
    }

    public function test_articles_can_save_chapter_ideas(): void
    {
        $article = $this->book([
            'title' => 'Finished Article',
            'kind' => Book::KIND_ARTICLE,
        ]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])
            ->call('openFeedback', $article->id)
            ->call('addFeedbackIdea', 'general')
            ->set('feedbackChapters.0.ideas.0.body', 'Useful quote')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved')
            ->assertSee('View feedback');

        $this->assertDatabaseHas('book_feedback_ideas', [
            'book_id' => $article->id,
            'body' => 'Useful quote',
        ]);
    }
}
