<?php

class Book
{
    private $id;
    private $title;
    private $author;
    private $price;
    private $description;
    private $category;
    private $image;
    private $created_at;
    private $publisher;
    private $category_id;

    // Constructor
    public function __construct(
        $id = null,
        $title = null,
        $author = null,
        $price = null,
        $description = null,
        $category = null,
        $image = null,
        $created_at = null,
        $publisher = null,
        $category_id = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->author = $author;
        $this->price = $price;
        $this->description = $description;
        $this->category = $category;
        $this->image = $image;
        $this->created_at = $created_at;
        $this->publisher = $publisher;
        $this->category_id = $category_id;
    }

    // Getters
    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function getPublisher()
    {
        return $this->publisher;
    }

    public function getCategoryId()
    {
        return $this->category_id;
    }

    // Setters
    public function setId($id)
    {
        $this->id = $id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setAuthor($author)
    {
        $this->author = $author;
    }

    public function setPrice($price)
    {
        $this->price = $price;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setCategory($category)
    {
        $this->category = $category;
    }

    public function setImage($image)
    {
        $this->image = $image;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
    }

    public function setCategoryId($category_id)
    {
        $this->category_id = $category_id;
    }

    // Convert object to array for database operations
    public function toArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'price' => $this->price,
            'description' => $this->description,
            'category' => $this->category,
            'image' => $this->image,
            'created_at' => $this->created_at,
            'publisher' => $this->publisher,
            'category_id' => $this->category_id
        ];
    }
}
?>