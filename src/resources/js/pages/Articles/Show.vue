<script setup lang="ts">
import { ChevronLeft, FileText } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';
import BaseButton from '@/components/ui/BaseButton.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { useArticles } from '@/composables/useArticles';
import { useTags } from '@/composables/useTags';
import { useRoutes } from '@/composables/useRoutes';
import { buildHeroImageUrl, buildAvatarUrl } from '@/utils/cloudinary';

interface Props {
    article: Article;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatPublishedDate, getAuthorDisplayName } = useArticles();
const routes = useRoutes();

const heroImageUrl = computed(() => buildHeroImageUrl(props.article.featuredImagePublicId));
const authorAvatarUrl = computed(() => buildAvatarUrl(props.article.author.avatarPublicId, 40));

const { categoryTag, additionalTags, hasTags } = useTags(computed(() => props.article.tags));
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6">
            <Link
                :href="routes.articles.index"
                class="inline-flex items-center rounded text-sm text-base-muted hover:text-base-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page"
            >
                <ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" />
                {{ t('common.back') }}
            </Link>
        </div>

        <article class="overflow-hidden rounded-lg bg-surface shadow dark:shadow-neutral-900/50">
            <img
                v-if="heroImageUrl"
                :src="heroImageUrl"
                :alt="props.article.title"
                class="h-48 w-full object-cover sm:h-64 md:h-80 lg:h-96"
            />
            <div
                v-else
                class="flex h-48 w-full items-center justify-center bg-linear-to-br from-primary-400 to-neutral-600 sm:h-64 md:h-80 lg:h-96"
            >
                <FileText class="h-24 w-24 text-white/50" aria-hidden="true" />
            </div>

            <div class="p-6 sm:p-8">
                <h1 class="mb-4 text-3xl font-bold text-base-primary sm:text-4xl">
                    {{ props.article.title }}
                </h1>

                <div class="mb-6 flex items-center text-base-secondary">
                    <img
                        v-if="authorAvatarUrl"
                        :src="authorAvatarUrl"
                        :alt="getAuthorDisplayName(props.article)"
                        class="mr-3 h-10 w-10 rounded-full"
                    />
                    <div
                        v-else
                        class="mr-3 flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-primary dark:bg-primary-900/30"
                    >
                        <span class="text-sm font-medium">
                            {{ getAuthorDisplayName(props.article).charAt(0).toUpperCase() }}
                        </span>
                    </div>
                    <div>
                        <p class="font-medium text-base-primary">
                            {{ getAuthorDisplayName(props.article) }}
                        </p>
                        <p v-if="props.article.publishedAt" class="text-sm text-primary">
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
                    Content is sanitized server-side via SanitizesHtml trait (HTMLPurifier).
                    @see App\Infrastructure\Factories\EloquentResponseDTOFactory
                -->
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div class="prose max-w-none" v-html="props.article.content" />

                <div class="mt-8 border-t border-default pt-6">
                    <Link :href="routes.articles.index">
                        <BaseButton variant="primary">
                            {{ t('common.viewAll') }} {{ t('common.articles').toLowerCase() }}
                        </BaseButton>
                    </Link>
                </div>
            </div>
        </article>
    </div>
</template>
