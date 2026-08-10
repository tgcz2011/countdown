# 安全加固部署说明

## 已修复的安全风险

### 1. Bug 10: 数据库密码硬编码
- **问题**: `config.php` 中数据库密码明文存储
- **修复**: 使用环境变量文件 `.env` 存储敏感信息
- **文件**: `config.php`, `includes/EnvLoader.php`

### 2. Bug 11: JS 中明文密码暴露
- **问题**: `admin/review.php` 中 `ADMIN_PASSWORD = 'admin888'` 暴露在JS中
- **修复**: 使用 session + token 认证，JS中只传递一次性token
- **文件**: `admin/review.php`, `includes/Auth.php`

### 3. Bug 12: HTTP Header 明文传输
- **问题**: API端点通过 `X-Admin-Password: admin888` 明文传输
- **修复**: 使用 `X-Auth-Token` 传递随机生成的session token
- **文件**: `api/approve_quote.php`, `api/get_pending_quotes.php`

## 部署步骤

### 1. 环境配置
```bash
# 复制环境变量模板
cp .env.example .env

# 编辑 .env 文件，填写实际配置
# DB_PASSWORD: 数据库密码
# ADMIN_PASSWORD: 管理员密码
# 其他配置按需修改
```

### 2. 文件权限设置
```bash
# 确保 .env 文件不可公开访问
chmod 600 .env

# 确保项目根目录有适当权限
chmod 755 .
```

### 3. 验证部署
1. 访问 `admin/review.php` 登录页面
2. 使用 `.env` 中配置的密码登录
3. 验证审核功能正常工作
4. 检查浏览器开发者工具，确认不再有明文密码传输

## 安全特性

### 1. 环境变量隔离
- 敏感信息存储在 `.env` 文件中
- `.env` 已添加到 `.gitignore`，不会提交到版本控制
- 通过 `EnvLoader` 类统一加载

### 2. Session + Token 认证
- 登录成功后生成随机 `auth_token`
- JS通过 `X-Auth-Token` header 传递
- Token与session绑定，有24小时有效期
- 使用 `hash_equals()` 防止时序攻击

### 3. 防御措施
- 密码验证使用 `hash_equals()`（常量时间比较）
- Session有超时机制（默认24小时）
- 随机token生成使用 `random_bytes()`
- 所有认证逻辑集中到 `Auth` 类

## 故障排除

### 1. 环境变量加载失败
- 检查 `.env` 文件是否存在
- 检查文件权限（应不可公开访问）
- 检查路径：`EnvLoader` 会自动查找 `.env`

### 2. 认证失败
- 检查session是否启用
- 检查 `ADMIN_PASSWORD` 配置是否正确
- 检查浏览器是否支持session

### 3. API调用失败
- 检查 `X-Auth-Token` header是否正确传递
- 检查session是否过期（24小时）
- 检查 `Auth::isAuthenticated()` 逻辑

## 后续安全建议

1. **HTTPS**: 在生产环境强制使用HTTPS
2. **密码策略**: 实现更强的密码策略（长度、复杂度）
3. **登录限制**: 添加登录失败次数限制
4. **审计日志**: 记录管理员操作日志
5. **定期更换**: 定期更换数据库密码和管理员密码

## 文件变更列表

```
新增文件:
  .env.example          - 环境变量模板
  .env                  - 实际环境变量（不提交）
  .gitignore           - 忽略敏感文件
  includes/EnvLoader.php - 环境变量加载器
  includes/Auth.php    - 认证辅助类

修改文件:
  config.php           - 使用环境变量
  admin/review.php     - 使用session/token认证
  api/approve_quote.php - 使用Auth类认证
  api/get_pending_quotes.php - 使用Auth类认证
```