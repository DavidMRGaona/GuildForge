<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Article } from '@/types/models';
import { useArticles } from '@/composables/useArticles';
import { useTags } from '@/composables/useTags';
import ImagePlaceholder from '@/components/ui/ImagePlaceholder.vue';
import TagBadge from '@/components/ui/TagBadge.vue';
import TagList from '@/components/ui/TagList.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    article: Article;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { getAuthorDisplayName, formatPublishedDate, getExcerpt } = useArticles();

const articleImageUrl = computed(() => buildCardImageUrl(props.article.featuredImagePublicId));

const { categoryTag, additionalTags } = useTags(computed(() => props.article.tags));
</script>

<template>
    <Link
        :href="`/articulos/${props.article.slug}`"
        :aria-label="t('a11y.viewArticle', { title: props.article.title })"
        class="group block overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:border-stone-700 dark:bg-stone-800 dark:shadow-stone-900/50 dark:focus:ring-offset-stone-900"
    >
        <!-- Image -->
        <div class="relative">
            <img
                v-if="articleImageUrl"
                :src="articleImageUrl"
                :alt="props.article.title"
                loading="lazy"
                class="aspect-video h-40 w-full object-cover"
            />
            <ImagePlaceholder v-else variant="article" height="h-40" icon-size="h-12 w-12" />

            <!-- Category badge as overlay on image -->
            <TagBadge
                v-if="categoryTag"
                :tag="categoryTag"
                :linkable="false"
                variant="category"
                content-type="articles"
                class="absolute left-3 top-3"
            />
        </div>

        <!-- Content -->
        <div class="p-4">
            <h3
                class="mb-2 line-clamp-2 text-lg font-semibold text-stone-900 group-hover:text-amber-600 dark:text-stone-100 dark:group-hover:text-amber-500"
            >
                {{ props.article.title }}
            </h3>

            <!-- Additional tags below title -->
            <TagList
                v-if="additionalTags.length"
                :tags="additionalTags"
                :linkable="false"
                content-type="articles"
                class="mb-2"
            />

            <p
                v-if="props.article.publishedAt"
                class="mb-2 text-sm text-stone-500 dark:text-stone-400"
            >
                {{ t('articles.by') }} {{ getAuthorDisplayName(props.article) }} ·
                {{ formatPublishedDate(props.article.publishedAt) }}
            </p>

            <p class="line-clamp-2 text-sm text-stone-600 dark:text-stone-300">
                {{ getExcerpt(props.article, 100) }}
            </p>
        </div>
    </Link>
</template>
