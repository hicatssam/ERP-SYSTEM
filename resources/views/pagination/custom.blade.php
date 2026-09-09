@if ($paginator->hasPages())

    <nav
        class="app-pagination"
        role="navigation"
        aria-label="التنقل بين الصفحات"
    >

        {{-- السابق --}}
        @if ($paginator->onFirstPage())

            <span
                class="app-page-btn app-page-arrow disabled"
                title="السابق"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M9 18l6-6-6-6"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>

        @else

            <a
                href="{{ $paginator->previousPageUrl() }}"
                class="app-page-btn app-page-arrow"
                title="السابق"
                rel="prev"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M9 18l6-6-6-6"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </a>

        @endif


        {{-- الصفحات --}}
        @foreach ($elements as $element)

            {{-- النقاط --}}
            @if (is_string($element))

                <span class="app-page-dots">
                    {{ $element }}
                </span>

            @endif


            {{-- أرقام الصفحات --}}
            @if (is_array($element))

                @foreach ($element as $page => $url)

                    @if ($page == $paginator->currentPage())

                        <span
                            class="app-page-btn active"
                            aria-current="page"
                        >
                            {{ $page }}
                        </span>

                    @else

                        <a
                            href="{{ $url }}"
                            class="app-page-btn"
                        >
                            {{ $page }}
                        </a>

                    @endif

                @endforeach

            @endif

        @endforeach


        {{-- التالي --}}
        @if ($paginator->hasMorePages())

            <a
                href="{{ $paginator->nextPageUrl() }}"
                class="app-page-btn app-page-arrow"
                title="التالي"
                rel="next"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M15 18l-6-6 6-6"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </a>

        @else

            <span
                class="app-page-btn app-page-arrow disabled"
                title="التالي"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M15 18l-6-6 6-6"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>

        @endif

    </nav>

@endif