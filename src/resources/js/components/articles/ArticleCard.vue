<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';
import { useArticles } from '@/composables/useArticles';
import BaseCard from '@/components/ui/BaseCard.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    article: Article;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { getAuthorDisplayName, formatPublishedDate, getExcerpt } = useArticles();

const articleImageUrl = computed(() => buildCardImageUrl(props.article.featuredImagePublicId));
</script>

<template>
    <Link
        :href="`/articulos/${props.article.slug}`"
        :aria-label="t('a11y.viewArticle', { title: props.article.title })"
        class="block transition-transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
    >
        <BaseCard :padding="false">
            <template #header>
                <div class="relative">
                    <img
                        v-if="articleImageUrl"
                        :src="articleImageUrl"
                        :alt="props.article.title"
                        loading="lazy"
                        class="aspect-video h-48 w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex aspect-video h-48 w-full items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600"
                    >
                        <svg
                            class="h-16 w-16 text-white/50"
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
                </div>
            </template>

            <div class="p-4">
                <h3 class="mb-2 line-clamp-2 text-lg font-semibold text-gray-900">
                    {{ props.article.title }}
                </h3>

                <p v-if="props.article.publishedAt" class="mb-3 text-sm text-blue-600">
                    {{ t('articles.by') }} {{ getAuthorDisplayName(props.article) }} · {{ formatPublishedDate(props.article.publishedAt) }}
                </p>

                <p class="line-clamp-3 text-sm text-gray-600">
                    {{ getExcerpt(props.article) }}
                </p>
            </div>
        </BaseCard>
    </Link>
</template>
