# SPDX-FileCopyrightText: 2026 André Wiesehoff
# SPDX-License-Identifier: AGPL-3.0-or-later

app_id := dashboard_links
sign_dir := $(CURDIR)/build/appstore
app_dir := $(sign_dir)/$(app_id)
cert_dir := $(HOME)/.nextcloud/certificates
occ := $(NEXTCLOUD_ROOT)/occ

.PHONY: appstore
appstore:
	rm -rf "$(sign_dir)"
	mkdir -p "$(app_dir)"
	rsync -a \
		--exclude-from="$(CURDIR)/.nextcloudignore" \
		--exclude=".git" \
		--exclude="build" \
		./ "$(app_dir)/"
	@if [ -n "$(NEXTCLOUD_ROOT)" ] && [ -f "$(occ)" ] && [ -f "$(cert_dir)/$(app_id).key" ] && [ -f "$(cert_dir)/$(app_id).crt" ]; then \
		php "$(occ)" integrity:sign-app \
			--privateKey="$(cert_dir)/$(app_id).key" \
			--certificate="$(cert_dir)/$(app_id).crt" \
			--path="$(app_dir)"; \
	else \
		echo "Skipping occ integrity:sign-app. Set NEXTCLOUD_ROOT and place $(app_id).key and $(app_id).crt in $(cert_dir)."; \
	fi
	tar -czf "$(sign_dir)/$(app_id).tar.gz" -C "$(sign_dir)" "$(app_id)"
	@echo "Wrote $(sign_dir)/$(app_id).tar.gz"
	@if [ -f "$(cert_dir)/$(app_id).key" ]; then \
		openssl dgst -sha512 -sign "$(cert_dir)/$(app_id).key" "$(sign_dir)/$(app_id).tar.gz" | openssl base64; \
	fi
