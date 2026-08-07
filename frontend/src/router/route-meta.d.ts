import 'vue-router'
import type { Role } from '@/types'

declare module 'vue-router' {
  interface RouteMeta {
    roles?: Role[]
    guestOnly?: boolean
  }
}
