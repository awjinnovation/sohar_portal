@if ($paginator->hasPages())
    <nav class="d-flex justify-content-between align-items-center" role="navigation" aria-label="Pagination Navigation">
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline-secondary disabled">
                    <i class="bi bi-chevron-right"></i> السابق
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm modern-pagination-btn">
                    <i class="bi bi-chevron-right"></i> السابق
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm modern-pagination-btn">
                    التالي <i class="bi bi-chevron-left"></i>
                </a>
            @else
                <span class="btn btn-sm btn-outline-secondary disabled">
                    التالي <i class="bi bi-chevron-left"></i>
                </span>
            @endif
        </div>

        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
            <div>
                <p class="small text-muted mb-0">
                    عرض
                    <span class="fw-semibold">{{ $paginator->firstItem() ?? 0 }}</span>
                    إلى
                    <span class="fw-semibold">{{ $paginator->lastItem() ?? 0 }}</span>
                    من
                    <span class="fw-semibold">{{ $paginator->total() }}</span>
                    نتيجة
                </p>
            </div>

            <div>
                <ul class="pagination pagination-modern mb-0">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                            <span class="page-link page-link-arrow" aria-hidden="true">
                                <i class="bi bi-chevron-right"></i>
                            </span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link page-link-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true">
                                <span class="page-link">{{ $element }}</span>
                            </li>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page">
                                        <span class="page-link">{{ $page }}</span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                    </li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link page-link-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                            <span class="page-link page-link-arrow" aria-hidden="true">
                                <i class="bi bi-chevron-left"></i>
                            </span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    @push('styles')
    <style>
        /* Modern Pagination Styles */
        .pagination-modern {
            gap: 6px;
        }

        .pagination-modern .page-link {
            border: 2px solid rgba(74, 144, 226, 0.15);
            border-radius: 10px;
            padding: 8px 14px;
            color: #4A90E2;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            margin: 0;
            min-width: 40px;
            text-align: center;
        }

        .pagination-modern .page-link:hover {
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.1) 0%, rgba(74, 144, 226, 0.15) 100%);
            border-color: #4A90E2;
            color: #3A7BC8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.2);
        }

        .pagination-modern .page-link:focus {
            box-shadow: 0 0 0 0.25rem rgba(74, 144, 226, 0.25);
            border-color: #4A90E2;
        }

        .pagination-modern .page-item.active .page-link {
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            border-color: #4A90E2;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(74, 144, 226, 0.4);
            transform: translateY(-2px);
        }

        .pagination-modern .page-item.disabled .page-link {
            background: #f8f9fa;
            border-color: rgba(0, 0, 0, 0.1);
            color: #adb5bd;
            cursor: not-allowed;
            transform: none;
        }

        .pagination-modern .page-link-arrow {
            padding: 8px 12px;
            font-size: 14px;
        }

        .modern-pagination-btn {
            background: white;
            border: 2px solid rgba(74, 144, 226, 0.15);
            border-radius: 10px;
            padding: 8px 16px;
            color: #4A90E2;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modern-pagination-btn:hover {
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            border-color: #4A90E2;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        /* RTL Support */
        [dir="rtl"] .pagination-modern {
            direction: rtl;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .pagination-modern .page-link {
                padding: 6px 10px;
                font-size: 0.875rem;
                min-width: 36px;
            }
        }

        /* Animation for page numbers */
        .pagination-modern .page-link {
            position: relative;
            overflow: hidden;
        }

        .pagination-modern .page-link::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(74, 144, 226, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .pagination-modern .page-link:hover::before {
            width: 100%;
            height: 100%;
        }

        .pagination-modern .page-link span,
        .pagination-modern .page-link i {
            position: relative;
            z-index: 1;
        }
    </style>
    @endpush
@endif
