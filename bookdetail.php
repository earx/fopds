<?php
/**
 * COPS (Calibre OPDS PHP Server) book detail script
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 *
 */

require_once ("config.php");
require_once ("book.php");

$book = Book::getBookById($_GET["id"]);
$authors = $book->getAuthors();
$translators= $book->getTranslators();

$tags = $book->getTags();
$serie = $book->getSerie();
$genres = $book->getGenres();
$reviews= $book->getReviews();
$book->getLinkArray();
 
?>
<div class="bookpopup">
    <div class="booke">
        <?php if ($book->hasCover): ?>
        <div class="cover">
            <a href="fetch.php?id=<?php echo $book->id ?>"><img src="fetch.php?id=<?php echo $book->id ?>&amp;height=150" alt="<?php echo localize("i18n.coversection") ?>" /></a>
        </div>
        <?php endif; ?>
        
        <?php foreach ($book->getDatas() as $data): ?>
        <div class="download">
            <div class="button buttonEffect"><a href="<?php echo $data->getHtmlLink(); ?>"><?php echo $data->format ?></a></div>
        </div>
        <?php endforeach; ?>
        
        <div class="entryTitle"><a rel="bookmark" href="<?php echo 'index.php' . $book->getUri () ?>"><img src="<?php echo getUrlWithVersion("images/Link.png") ?>" alt="permalink" /></a><?php echo htmlspecialchars ($book->title) ?></div>
        <div class="entrySection">
            <span><?php echo localize("authors.title") ?></span>
            <div class="buttonEffect pad6">
<?php
            $i = 0;
            foreach ($authors as $author) {
                if ($i > 0) echo ", ";
?>
                <a href="index.php<?php echo str_replace ("&", "&amp;", $author->getUri ()) ?>"><?php echo htmlspecialchars ($author->name) ?></a>
<?php
            }
?>
            </div>
        </div>
        
<?php if ( !empty($translators) ): ?>
        <div class="entrySection">
            <span><?php echo localize("translators.title") ?></span>
            <div class="buttonEffect pad6">
<?php
            $i = 0;
            foreach ($translators as $author) {
                if ($i > 0) echo ", ";
?>
                <a href="index.php<?php echo str_replace ("&", "&amp;", $author->getUri ()) ?>"><?php echo htmlspecialchars ($author->name) ?></a>
<?php
            }
?>
            </div>
        </div>
<?php endif; ?>        
        
        <div class="entrySection">
            <span><?php echo localize("tags.title") ?></span>
            <div class="buttonEffect pad6">
<?php
            $i = 0;
            foreach ($tags as $tag) {
                if ($i > 0) echo ", ";
?>
                <a href="index.php<?php echo str_replace ("&", "&amp;", $tag->getUri ()) ?>"><?php echo htmlspecialchars ($tag->name) ?></a>
<?php
            }
?>
            </div>
        </div>

<?php if (!is_null ($serie)): ?>
        <div class="entrySection">
            <div class="buttonEffect pad6">
                <a href="index.php<?php echo str_replace ("&", "&amp;", $serie->getUri ()) ?>"><?php echo localize("series.title") ?></a>
            </div>
            <?php echo str_format (localize ("content.series.data"), $book->seriesIndex, htmlspecialchars ($serie->name)) ?>
        </div>
<?php endif;  ?>

<?php if (!is_null ($genres)): ?>
        <div class="entrySection">
            <div class="buttonEffect pad6">
                <a href="index.php<?php //echo str_replace ("&", "&amp;", $genres->getUri ()) ?>"><?php echo localize("genresword.title") ?></a>
            </div>
            TODO
            <?php //echo str_format (localize ("content.genres.data"), $book->GenresIndex, htmlspecialchars ($serie->name)) ?>
        </div>
<?php endif;  ?>

        <div class="entrySection">
            <span><?php echo localize("config.Language.label") ?></span>
            <?php echo $book->getLanguages () ?>
        </div>
    </div>
    <div class="clearer" />
    <hr />
    <div><?php echo localize("content.summary") ?></div>
    <div class="content" style="max-width:700px;"><?php echo $book->getComment (false) ?></div>

<?php if (!is_null ($reviews)): ?>
        <div class="entrySection">
            <div class="buttonEffect pad6">
                <a href="index.php<?php //echo str_replace ("&", "&amp;", $serie->getUri ()) ?>"><?php //echo localize("genresword.title") ?></a>
            </div>
            TODO: user comments - libreviews
            <?php //echo str_format (localize ("content.genres.data"), $book->GenresIndex, htmlspecialchars ($serie->name)) ?>
        </div>
<?php endif;  ?>

    <hr />
</div>