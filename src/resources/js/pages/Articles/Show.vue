<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { useArticles } from '@/composables/useArticles';
import { useTags } from '@/composables/useTags';
import { useSeo } from '@/composables/useSeo';
import { buildHeroImageUrl, buildAvatarUrl } from '@/utils/cloudinary';

interface Props {
    article: Article;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatPublishedDate, getAuthorDisplayName } = useArticles();

const heroImageUrl = computed(() => buildHeroImageUrl(props.article.featuredImagePublicId));
const authorAvatarUrl = computed(() => buildAvatarUrl(props.article.author.avatarPublicId, 40));

const { categoryTag, additionalTags, hasTags } = useTags(computed(() => props.article.tags));

useSeo({
    title: props.article.title,
    description: props.article.excerpt,
    image: heroImageUrl.value,
    type: 'article',
});
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    href="/articulos"
                    class="inline-flex items-center rounded text-sm text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:text-stone-400 dark:hover:text-stone-300 dark:focus:ring-offset-stone-900"
                >
                    <svg
                        class="mr-1 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    {{ t('common.back') }}
                </Link>
            </div>

            <article
                class="overflow-hidden rounded-lg bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50"
            >
                <img
                    v-if="heroImageUrl"
                    :src="heroImageUrl"
                    :alt="props.article.title"
                    class="h-48 w-full object-cover sm:h-64 md:h-80 lg:h-96"
                />
                <div
                    v-else
                    class="flex h-48 w-full items-center justify-center bg-linear-to-br from-amber-400 to-slate-600 sm:h-64 md:h-80 lg:h-96"
                >
                    <svg
                        class="h-24 w-24 text-white/50"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                <div class="p-6 sm:p-8">
                    <h1
                        class="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl dark:text-stone-100"
                    >
                        {{ props.article.title }}
                    </h1>

                    <div class="mb-6 flex items-center text-gray-600 dark:text-stone-400">
                        <img
                            v-if="authorAvatarUrl"
                            :src="authorAvatarUrl"
                            :alt="getAuthorDisplayName(props.article)"
                            class="mr-3 h-10 w-10 rounded-full"
                        />
                        <div
                            v-else
                            class="mr-3 flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                        >
                            <span class="text-sm font-medium">
                                {{ getAuthorDisplayName(props.article).charAt(0).toUpperCase() }}
                            </span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-stone-100">
                                {{ getAuthorDisplayName(props.article) }}
                            </p>
                            <p v-if="props.article.publishedAt" class="text-sm text-amber-600">
                                {{ t('articles.publishedAt') }}
                                {{ formatPublishedDate(props.article.publishedAt) }}
                            </p>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="hasTags" class="mb-6 flex flex-wrap items-center gap-2">
                        <TagBadge
                            v-if="categoryTag"
                            :tag="categoryTag"
                            variant="category"
                            content-type="articles"
                        />
                        <TagList
                            v-if="additionalTags.length"
                            :tags="additionalTags"
                            content-type="articles"
                        />
                    </div>

                    <!--
                        SECURITY: v-html is used to render rich text content from the article.
                        Content MUST be sanitized server-side before storage in the database.
                        XSS RISK: If content is not properly sanitized, this could execute malicious scripts.
                        @see App\Filament\Resources\ArticleResource - content field validation
                        @see App\Domain\Entities\Article - domain entity
                    -->
                    <div class="prose max-w-none" v-html="props.article.content" />

                    <div class="mt-8 border-t border-gray-200 pt-6 dark:border-stone-700">
                        <Link href="/articulos">
                            <BaseButton variant="primary">
                                {{ t('common.viewAll') }} {{ t('common.articles').toLowerCase() }}
                            </BaseButton>
                        </Link>
                    </div>
                </div>
            </article>
        </div>
    </DefaultLayout>
</template>
