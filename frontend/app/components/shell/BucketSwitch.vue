<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui'

const props = defineProps<{ current: string | null; section: string }>()
const { t } = useLang()
const { buckets } = useBuckets()
const open = ref(false)
const outside = useOutsideClose()
const selected = computed(() => buckets.value.find((b) => b.id === props.current) ?? null)

// The section is kept when switching: files of one bucket → files of the other.
const target = (id: string) => '/buckets/' + id + (props.section === 'overview' ? '' : '/' + props.section)
</script>

<template>
  <span id="bucket-switch-label" class="nav-section">{{ t('nav.bucket') }}</span>
  <DropdownMenuRoot v-model:open="open" :modal="false">
    <div class="cselect" :class="{ 'is-open': open }">
      <DropdownMenuTrigger as-child>
        <button type="button" class="cselect__btn" aria-labelledby="bucket-switch-label">
          <span class="cselect__value">
            <template v-if="selected">
              <span class="status-dot" :class="{ 'status-dot--mid': !isActive(selected.status) }" />
              <span class="cselect__name">{{ selected.name }}</span>
            </template>
            <template v-else>
              <span class="status-dot status-dot--light" />
              <span class="cselect__name">{{ t('bucket.none') }}</span>
            </template>
          </span>
          <UiIcon name="chevron-down" size="sm" class="cselect__chev" />
        </button>
      </DropdownMenuTrigger>
    </div>
    <DropdownMenuPortal>
      <DropdownMenuContent class="cselect__menu is-reka" @interact-outside="outside.onInteractOutside" @close-auto-focus="outside.onCloseAutoFocus" align="start" :side-offset="6">
        <DropdownMenuItem
          v-for="bucket in buckets"
          :key="bucket.id"
          class="cselect__option"
          :class="{ 'is-selected': bucket.id === current }"
          @select="navigateTo(target(bucket.id))"
        >
          <span class="status-dot" :class="{ 'status-dot--mid': !isActive(bucket.status) }" />
          <span class="cselect__name">{{ bucket.name }}</span>
          <UiIcon name="check" class="icon--check" />
        </DropdownMenuItem>
        <span v-if="buckets.length === 0" class="cselect__option text-muted">{{ t('bucket.empty') }}</span>
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>
