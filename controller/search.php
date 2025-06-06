<?php

class SearchController
{
    private $bookModel;

    public function __construct($bookModel)
    {
        $this->bookModel = $bookModel;
    }

    public function search($keyword, $page = 1, $perPage = 20)
    {
        $keyword = trim($keyword);
        if (empty($keyword)) {
            return [
                'books' => [],
                'totalBooks' => 0,
                'currentPage' => $page,
                'totalPages' => 0
            ];
        }

        $books = $this->bookModel->searchBooks($keyword, $page, $perPage);
        $totalBooks = $this->bookModel->getTotalSearchResults($keyword);
        $totalPages = ceil($totalBooks / $perPage);

        return [
            'books' => $books,
            'totalBooks' => $totalBooks,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'keyword' => $keyword
        ];
    }
}