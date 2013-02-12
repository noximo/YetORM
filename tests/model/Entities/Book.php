<?php


/**
 * @property-read int $id
 * @property string $book_title
 * @property string $written
 * @property bool $available
 */
class Book extends YetORM\Entity
{

	/** @return Author */
	function getAuthor()
	{
		return new Author($this->row->author);
	}



	/**
	 * @param  Author
	 * @return Book
	 */
	function setAuthor(Author $author)
	{
		$this->row->author_id = $author->getId();
		return $this;
	}



	/** @return YetORM\EntityCollection */
	function getTags()
	{
		return $this->getMany('Tag', 'book_tag', 'tag');
	}

}
