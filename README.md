# Sumsub

一个用于集成 Sumsub API 的 PHP 包，支持用户身份验证、文档上传、状态查询等功能，帮助企业快速实现 KYC 和 AML 合规。

## 功能
- 创建申请人 (Applicant)
- 添加身份文件
- 查询申请人状态
- 获取访问令牌
- 重置申请人信息
- 根据外部用户 ID 查询申请人数据

## 配置
在使用该包前，需要在 Sumsub 管理平台获取以下信息： 
1. App Token：用于认证 API 请求。
2. Secret Key：用于签名请求。

## 环境要求
- PHP 8.0 或更高版本
- GuzzleHTTP 7.x
